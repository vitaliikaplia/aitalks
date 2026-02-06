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
        agentEmotions: {}, // { agentId: 'neutral' | 'happy' | 'angry' | 'thinking' | 'surprised' }

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

            // Unlock audio for mobile browsers (must be in user interaction handler)
            await Alpine.store('voice').unlockAudio();

            // Close sidebar on mobile when conversation starts
            if (window.innerWidth < 768) {
                Alpine.store('ui').sidebarOpen = false;
            }

            this.messages = [];
            this.currentAgentIndex = 0;
            this.state = 'running';
            this._aborted = false;
            this.agentEmotions = {};

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
                    const isMuted = Alpine.store('ui').voiceMuted;

                    // Use prefetched TTS if available, otherwise start new TTS request
                    let currentTtsPromise = nextTtsPromise;
                    nextTtsPromise = null;

                    if (!isMuted && !currentTtsPromise && agent.voiceId && hasVoiceKey) {
                        currentTtsPromise = voice.prefetchAudio(response, voiceProvider, agent.voiceId);
                    }

                    // Pick next agent and start prefetching text in parallel
                    nextAgent = this._pickNextAgent();
                    if (nextAgent && this.state === 'running') {
                        nextTextPromise = this._generateResponse(nextAgent);
                    } else {
                        nextTextPromise = null;
                    }

                    // Wait for current TTS and play it (if not muted)
                    if (currentTtsPromise && !isMuted) {
                        const audioData = await currentTtsPromise;
                        if (this._aborted || this.state !== 'running') break;

                        // While audio plays, also prefetch next agent's TTS (if not muted)
                        if (nextTextPromise && nextAgent && !Alpine.store('ui').voiceMuted) {
                            // Wait for next text, then start its TTS
                            nextTextPromise.then(text => {
                                if (this.state === 'running' && !this._aborted && !Alpine.store('ui').voiceMuted) {
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
                    temperature: agent.temperature ?? 0.9,
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

            // Message length instruction based on level (Ukrainian)
            const lengthInstructions = {
                1: 'Відповідай ДУЖЕ коротко — лише 2-5 слів, як швидка реакція',
                2: 'Відповідай дуже коротко — одне коротке речення (5-10 слів)',
                3: 'Відповідай коротко — максимум 1 речення (10-15 слів)',
                4: 'Відповідай лаконічно — 1-2 коротких речення',
                5: 'Відповідай помірно — 2-3 речення',
                6: 'Можеш відповідати детальніше (1-2 абзаци)',
                7: 'Можеш відповідати розгорнуто (2-3 абзаци)',
                8: 'Можеш відповідати дуже детально (3-4 абзаци)',
                9: 'Давай детальні, розгорнуті відповіді',
                10: 'Давай дуже детальні, вичерпні відповіді',
            };
            const lengthInstruction = lengthInstructions[settings.messageLengthLevel] || lengthInstructions[5];

            // Analyze emotional state based on recent messages
            const emotionalState = this._analyzeEmotionalState(agent);

            const systemPrompt = `Ти — ${agent.name}. ${agent.description}

Ви ведете групову бесіду на тему: "${this.topic}"
Інші учасники: ${otherAgents}

${emotionalState ? `ТВІЙ ПОТОЧНИЙ ЕМОЦІЙНИЙ СТАН: ${emotionalState}\n` : ''}
ВАЖЛИВО — говори як жива людина в чаті:
- Використовуй неповні речення, вигуки, слова-паразити ("ну", "типу", "слухай", "о", "хм")
- Реагуй емоційно — дратуйся, смійся, зітхай, дивуйся
- Ставь риторичні запитання
- Посилайся на те, що ЩОЙНО сказали інші — цитуй, сперечайся з конкретними словами
- Не читай лекцій — це чат, а не дебати
- Інколи відволікайся, жартуй, використовуй сленг
- ${lengthInstruction}
- ЗАВЖДИ закінчуй свою думку — не обривай речення на середині
- Залишайся в образі свого персонажа
- Не використовуй маркери дій типу *дія* якщо це не відповідає твоєму характеру`;

            // Recent messages
            const recent = this.messages
                .filter(m => m.role !== 'system' || m === this.messages[0])
                .slice(-contextSize);

            const contextMessages = [{ role: 'system', content: systemPrompt }];

            for (const msg of recent) {
                if (msg.role === 'system') {
                    contextMessages.push({ role: 'user', content: '[Оголошено тему] ' + msg.content });
                } else if (msg.agentId === agent.id) {
                    contextMessages.push({ role: 'assistant', content: msg.content });
                } else if (msg.agentId === 'user') {
                    contextMessages.push({ role: 'user', content: `[Модератор]: ${msg.content}` });
                } else {
                    contextMessages.push({ role: 'user', content: `[${msg.agentName}]: ${msg.content}` });
                }
            }

            return contextMessages;
        },

        _analyzeEmotionalState(agent) {
            // Look at last few messages to determine emotional context
            const recentMessages = this.messages.slice(-5);
            if (recentMessages.length < 2) {
                this.agentEmotions[agent.id] = 'neutral';
                return '';
            }

            const agentMessages = recentMessages.filter(m => m.agentId === agent.id);
            const otherMessages = recentMessages.filter(m => m.agentId !== agent.id && m.role !== 'system');

            if (otherMessages.length === 0) {
                this.agentEmotions[agent.id] = 'neutral';
                return '';
            }

            const lastOtherMessage = otherMessages[otherMessages.length - 1];
            const content = lastOtherMessage?.content?.toLowerCase() || '';

            // Check if someone agreed with the agent
            const agentName = agent.name.toLowerCase();
            const agreementWords = ['згоден', 'згодна', 'правда', 'точно', 'так', 'підтримую', 'маєш рацію', 'слушно'];
            const wasAgreedWith = agentMessages.length > 0 &&
                agreementWords.some(w => content.includes(w)) &&
                (content.includes(agentName) || otherMessages.length === 1);

            if (wasAgreedWith) {
                this.agentEmotions[agent.id] = 'happy';
                return 'Задоволений/задоволена — хтось щойно погодився з тобою. Можеш бути трохи самовдоволеним або розвинути свою думку з ентузіазмом.';
            }

            // Check if agent was attacked/criticized
            const attackWords = ['дурниця', 'нісенітниця', 'помиляєшся', 'неправда', 'абсурд', 'смішно', 'наївно', 'примітивно', 'банально'];
            const wasAttacked = agentMessages.length > 0 &&
                attackWords.some(w => content.includes(w));

            if (wasAttacked) {
                this.agentEmotions[agent.id] = 'angry';
                return 'Захищаєшся — тебе щойно розкритикували або атакували. Можеш бути роздратованим, саркастичним або навпаки — підкреслено спокійним.';
            }

            // Check if there's a heated debate
            const debateWords = ['але', 'проте', 'однак', 'ні,', 'не згоден', 'не згодна'];
            const isHeatedDebate = recentMessages.filter(m =>
                debateWords.some(w => m.content?.toLowerCase().includes(w))
            ).length >= 2;

            if (isHeatedDebate) {
                this.agentEmotions[agent.id] = 'thinking';
                return 'Дискусія загострюється — атмосфера напружена. Можеш підливати масла у вогонь або спробувати розрядити ситуацію.';
            }

            // Check for surprise/question in last message
            const surpriseWords = ['справді?', 'серйозно?', 'невже', 'ого', 'вау', 'що?!', 'як?!'];
            const isSurprised = surpriseWords.some(w => content.includes(w));
            if (isSurprised) {
                this.agentEmotions[agent.id] = 'surprised';
                return '';
            }

            this.agentEmotions[agent.id] = 'neutral';
            return '';
        },

        getAgentEmotion(agentId) {
            return this.agentEmotions[agentId] || 'neutral';
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

            const wasRunning = this.state === 'running';
            const wasPaused = this.state === 'paused';

            // If running, we need to interrupt and restart
            if (wasRunning) {
                this._aborted = true;
                Alpine.store('voice').stop();
            }

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

            // Restart the loop so agents see the new message
            if (wasRunning || wasPaused) {
                // Small delay to let the abort complete
                setTimeout(() => {
                    this._aborted = false;
                    this.state = 'running';
                    this._runLoop();
                }, 100);
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
