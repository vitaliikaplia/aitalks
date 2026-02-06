document.addEventListener('alpine:init', () => {

    Alpine.store('conversation', {
        messages: [],
        state: 'idle', // idle | running | paused | waiting
        topic: '',
        currentAgentIndex: 0,
        isGenerating: false,
        interventionText: '',
        showIntervention: false,
        _aborted: false,
        savedConversations: [],
        saveDialogName: '',
        showSaveDialog: false,
        showLoadDialog: false,

        init() {
            this.savedConversations = Storage.listConversations();
        },

        async start() {
            const agents = Alpine.store('agents');
            const settings = Alpine.store('settings');

            if (!agents.canStart()) {
                Alpine.store('ui').notify('Потрібно мінімум 2 агенти з іменем та описом', 'error');
                return;
            }
            if (!this.topic.trim()) {
                Alpine.store('ui').notify('Спочатку введіть тему', 'error');
                return;
            }

            // Check API keys for all enabled agents
            const enabledAgents = agents.list.filter(a => a.enabled && a.name.trim() && a.description.trim());
            const missingKeys = [];
            for (const agent of enabledAgents) {
                const apiKey = settings.getKeyForProvider(agent.provider);
                if (!apiKey) {
                    missingKeys.push({ agent: agent.name, provider: agent.provider });
                }
            }

            if (missingKeys.length > 0) {
                const providers = [...new Set(missingKeys.map(m => m.provider))];
                const providerNames = {
                    openai: 'OpenAI',
                    claude: 'Anthropic Claude',
                    gemini: 'Google Gemini',
                };
                const names = providers.map(p => providerNames[p] || p).join(', ');
                Alpine.store('ui').notify(`Додайте API ключ для: ${names}`, 'error');
                return;
            }

            this.messages = [];
            this.currentAgentIndex = 0;
            this.state = 'running';
            this._aborted = false;

            // Add topic as system message
            this.messages.push({
                role: 'system',
                agentId: null,
                agentName: 'Система',
                agentColor: '#888',
                content: 'Тема: ' + this.topic,
                timestamp: Date.now(),
            });

            this._runLoop();
        },

        async _runLoop() {
            // Pipeline state: prefetch text + TTS for next agent while current speaks
            let nextTextPromise = null;
            let nextTtsPromise = null;
            let nextAgent = null;
            let nextResponse = null;

            const voice = Alpine.store('voice');

            while (this.state === 'running') {
                if (this._aborted) break;

                const agent = nextAgent || this._pickNextAgent();
                if (!agent) break;

                this.isGenerating = true;

                try {
                    // Get current agent's response (from prefetch or generate now)
                    const response = nextResponse !== null
                        ? nextResponse
                        : await this._generateResponse(agent);

                    if (this._aborted || this.state !== 'running') break;

                    // Add message to chat
                    this.messages.push({
                        role: 'assistant',
                        agentId: agent.id,
                        agentName: agent.name,
                        agentColor: agent.color,
                        content: response,
                        timestamp: Date.now(),
                    });

                    this._scrollToBottom();
                    this.isGenerating = false;

                    // Determine voice settings for current agent
                    const voiceProvider = agent.voiceProvider || 'openai_tts';
                    const hasVoiceKey = voiceProvider === 'openai_tts'
                        ? Alpine.store('settings').openaiKey
                        : Alpine.store('settings').elevenlabsKey;

                    // Use prefetched TTS if available, otherwise start new TTS request
                    let currentTtsPromise = nextTtsPromise;
                    nextTtsPromise = null;

                    if (!currentTtsPromise && agent.voiceId && hasVoiceKey) {
                        currentTtsPromise = voice.prefetchAudio(response, voiceProvider, agent.voiceId);
                    }

                    // Pick next agent and start prefetching text in parallel
                    nextAgent = this._pickNextAgent();
                    if (nextAgent && this.state === 'running') {
                        nextTextPromise = this._generateResponse(nextAgent);
                    } else {
                        nextTextPromise = null;
                    }

                    // Wait for current TTS and play it
                    if (currentTtsPromise) {
                        const audioData = await currentTtsPromise;
                        if (this._aborted || this.state !== 'running') break;

                        // While audio plays, also prefetch next agent's TTS
                        if (nextTextPromise && nextAgent) {
                            // Wait for next text, then start its TTS
                            nextTextPromise.then(text => {
                                if (this.state === 'running' && !this._aborted) {
                                    nextResponse = text;
                                    const nextVoiceProvider = nextAgent.voiceProvider || 'openai_tts';
                                    const nextHasKey = nextVoiceProvider === 'openai_tts'
                                        ? Alpine.store('settings').openaiKey
                                        : Alpine.store('settings').elevenlabsKey;
                                    if (nextAgent.voiceId && nextHasKey) {
                                        nextTtsPromise = voice.prefetchAudio(text, nextVoiceProvider, nextAgent.voiceId);
                                    }
                                }
                            }).catch(() => {});
                        }

                        // Play current audio (blocking until done)
                        await voice.playPrefetched(audioData, agent.id);
                    } else {
                        // No TTS - just wait for next text
                        if (nextTextPromise) {
                            nextResponse = await nextTextPromise;
                            nextTextPromise = null;
                        }
                    }

                    if (this._aborted || this.state !== 'running') break;

                    // If we started prefetching next text during TTS, collect result
                    if (nextTextPromise) {
                        try {
                            nextResponse = await nextTextPromise;
                        } catch (e) {
                            nextResponse = null;
                        }
                        nextTextPromise = null;
                    }

                    // Small pause between turns (shorter if TTS was prefetched)
                    await this._sleep(100);

                } catch (err) {
                    console.error('Generation error:', err);
                    Alpine.store('ui').notify('Помилка: ' + err.message, 'error');
                    this.isGenerating = false;
                    this.state = 'paused';
                    nextTextPromise = null;
                    nextTtsPromise = null;
                    nextResponse = null;
                    break;
                }
            }
        },

        _pickNextAgent() {
            const agents = Alpine.store('agents').list.filter(a =>
                a.enabled && a.name.trim() && a.description.trim()
            );
            if (agents.length === 0) return null;

            const settings = Alpine.store('settings');

            if (settings.turnMode === 'random') {
                // Avoid same agent twice in a row
                let pick;
                let attempts = 0;
                do {
                    pick = agents[Math.floor(Math.random() * agents.length)];
                    attempts++;
                } while (
                    this.messages.length > 0 &&
                    pick.id === this.messages[this.messages.length - 1].agentId &&
                    agents.length > 1 &&
                    attempts < 10
                );
                return pick;
            } else {
                // Round-robin
                const agent = agents[this.currentAgentIndex % agents.length];
                this.currentAgentIndex = (this.currentAgentIndex + 1) % agents.length;
                return agent;
            }
        },

        async _generateResponse(agent) {
            const settings = Alpine.store('settings');
            const apiKey = settings.getKeyForProvider(agent.provider);
            if (!apiKey) {
                throw new Error('Немає API ключа для ' + agent.provider + '. Встановіть в Налаштуваннях.');
            }

            const contextMessages = this._buildContext(agent);

            const response = await fetch('/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'chat',
                    provider: agent.provider,
                    api_key: apiKey,
                    model: agent.model,
                    messages: contextMessages,
                    length_level: settings.messageLengthLevel || 5,
                }),
            });

            if (!response.ok) {
                const err = await response.json().catch(() => ({ error: 'Unknown error' }));
                throw new Error(err.error || 'API request failed');
            }

            const data = await response.json();
            return data.content || '';
        },

        _buildContext(agent) {
            const settings = Alpine.store('settings');
            const agents = Alpine.store('agents').list;
            const contextSize = settings.contextSize;

            // System prompt
            const otherAgents = agents
                .filter(a => a.id !== agent.id && a.name.trim())
                .map(a => a.name)
                .join(', ');

            // Message length instruction based on level
            const lengthInstructions = {
                1: 'Keep responses EXTREMELY brief - just 2-5 words maximum, like quick reactions',
                2: 'Keep responses very short - one brief sentence (5-10 words)',
                3: 'Keep responses short - 1 sentence maximum (10-15 words)',
                4: 'Keep responses concise - 1-2 short sentences',
                5: 'Keep responses moderate - 2-3 sentences',
                6: 'You may provide moderate detail (1-2 paragraphs)',
                7: 'You may provide good detail (2-3 paragraphs)',
                8: 'You may provide extensive detail (3-4 paragraphs)',
                9: 'Provide detailed, comprehensive responses',
                10: 'Provide very detailed, thorough responses',
            };
            const lengthInstruction = lengthInstructions[settings.messageLengthLevel] || lengthInstructions[5];

            const systemPrompt = `You are ${agent.name}. ${agent.description}

You are in a group conversation about: "${this.topic}"
Other participants: ${otherAgents}

Rules:
- Stay in character
- ${lengthInstruction}
- React to what others have said
- Be natural and conversational
- Do not use action markers like *actions* unless it fits your character`;

            // Recent messages
            const recent = this.messages
                .filter(m => m.role !== 'system' || m === this.messages[0])
                .slice(-contextSize);

            const contextMessages = [{ role: 'system', content: systemPrompt }];

            for (const msg of recent) {
                if (msg.role === 'system') {
                    contextMessages.push({ role: 'user', content: '[Topic announced] ' + msg.content });
                } else if (msg.agentId === agent.id) {
                    contextMessages.push({ role: 'assistant', content: msg.content });
                } else if (msg.agentId === 'user') {
                    contextMessages.push({ role: 'user', content: `[Moderator]: ${msg.content}` });
                } else {
                    contextMessages.push({ role: 'user', content: `[${msg.agentName}]: ${msg.content}` });
                }
            }

            return contextMessages;
        },

        pause() {
            if (this.state === 'running') {
                this.state = 'paused';
            }
        },

        resume() {
            if (this.state === 'paused') {
                this.state = 'running';
                this._runLoop();
            }
        },

        stop() {
            this._aborted = true;
            this.state = 'idle';
            this.isGenerating = false;
            Alpine.store('voice').stop();
        },

        intervene() {
            if (!this.interventionText.trim()) return;

            const wasPaused = this.state === 'paused';

            this.messages.push({
                role: 'user',
                agentId: 'user',
                agentName: 'Ви',
                agentColor: '#FFFFFF',
                content: this.interventionText,
                timestamp: Date.now(),
            });

            this.interventionText = '';
            this.showIntervention = false;
            this._scrollToBottom();

            if (wasPaused) {
                this.resume();
            }
        },

        // Save/Load conversations
        saveConversation() {
            if (!this.saveDialogName.trim()) {
                Alpine.store('ui').notify('Введіть назву розмови', 'error');
                return;
            }
            Storage.saveConversation(this.saveDialogName, {
                topic: this.topic,
                messages: this.messages,
                agents: Alpine.store('agents').list,
                savedAt: Date.now(),
            });
            this.savedConversations = Storage.listConversations();
            this.showSaveDialog = false;
            this.saveDialogName = '';
            Alpine.store('ui').notify('Розмову збережено');
        },

        loadConversation(name) {
            const data = Storage.loadConversation(name);
            if (!data) return;

            this.stop();
            this.topic = data.topic;
            this.messages = data.messages;
            Alpine.store('agents').list = data.agents;
            Alpine.store('agents').persist();
            this.showLoadDialog = false;
            Alpine.store('ui').notify('Розмову завантажено: ' + name);
        },

        deleteConversation(name) {
            Storage.deleteConversation(name);
            this.savedConversations = Storage.listConversations();
            Alpine.store('ui').notify('Розмову видалено');
        },

        _scrollToBottom() {
            setTimeout(() => {
                const el = document.getElementById('chat-messages');
                if (el) el.scrollTop = el.scrollHeight;
            }, 50);
        },

        _sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
    });
});
