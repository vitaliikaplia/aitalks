document.addEventListener('alpine:init', () => {

    Alpine.store('voice', {
        isSpeaking: false,
        speakingAgentId: null,
        mouthOpenness: 0,
        _audioCtx: null,
        _analyser: null,
        _source: null,
        _animFrame: null,
        _resolve: null,

        _getAudioContext() {
            if (!this._audioCtx) {
                this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            return this._audioCtx;
        },

        async speak(text, voiceProvider, voiceId, agentId) {
            const settings = Alpine.store('settings');

            // Determine API key based on voice provider
            let apiKey;
            if (voiceProvider === 'openai_tts') {
                apiKey = settings.openaiKey;
            } else {
                apiKey = settings.elevenlabsKey;
            }

            if (!apiKey || !voiceId) return;

            // Trim text for TTS (avoid very long audio)
            const ttsText = text.length > 500 ? text.substring(0, 500) + '...' : text;

            try {
                const requestBody = {
                    action: 'tts',
                    tts_provider: voiceProvider,
                    api_key: apiKey,
                    voice_id: voiceId,
                    text: ttsText,
                };

                // Add speed parameter for OpenAI TTS
                if (voiceProvider === 'openai_tts') {
                    requestBody.speed = settings.speechRate || 1.0;
                }

                const response = await fetch('/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(requestBody),
                });

                if (!response.ok) {
                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        const err = await response.json();
                        console.error('TTS error:', err.error);
                    } else {
                        console.error('TTS error:', response.status);
                    }
                    return;
                }

                const audioData = await response.arrayBuffer();
                await this._playWithAnimation(audioData, agentId);
            } catch (err) {
                console.error('TTS error:', err);
            }
        },

        _playWithAnimation(audioData, agentId) {
            return new Promise((resolve) => {
                this._resolve = resolve;
                const ctx = this._getAudioContext();

                const play = async () => {
                    if (ctx.state === 'suspended') {
                        await ctx.resume();
                    }

                    const buffer = await ctx.decodeAudioData(audioData.slice(0));

                    this._analyser = ctx.createAnalyser();
                    this._analyser.fftSize = 256;
                    this._analyser.smoothingTimeConstant = 0.3;

                    this._source = ctx.createBufferSource();
                    this._source.buffer = buffer;
                    this._source.connect(this._analyser);
                    this._analyser.connect(ctx.destination);

                    this.isSpeaking = true;
                    this.speakingAgentId = agentId;

                    this._source.onended = () => {
                        this._stopAnimation();
                        resolve();
                    };

                    this._source.start(0);
                    this._animate();
                };

                play().catch((err) => {
                    console.error('Audio decode error:', err);
                    this._stopAnimation();
                    resolve();
                });
            });
        },

        _animate() {
            if (!this._analyser || !this.isSpeaking) return;

            const dataArray = new Uint8Array(this._analyser.frequencyBinCount);
            this._analyser.getByteFrequencyData(dataArray);

            // Calculate average amplitude from lower frequencies (voice range)
            const voiceRange = dataArray.slice(0, 20);
            let sum = 0;
            for (let i = 0; i < voiceRange.length; i++) {
                sum += voiceRange[i];
            }
            const avg = sum / voiceRange.length;

            // Normalize to 0-1, with some smoothing
            this.mouthOpenness = Math.min(1, avg / 128);

            this._animFrame = requestAnimationFrame(() => this._animate());
        },

        _stopAnimation() {
            this.isSpeaking = false;
            this.speakingAgentId = null;
            this.mouthOpenness = 0;
            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
                this._animFrame = null;
            }
        },

        stop() {
            if (this._source) {
                try { this._source.stop(); } catch (e) { }
                this._source = null;
            }
            this._stopAnimation();
            if (this._resolve) {
                this._resolve();
                this._resolve = null;
            }
        },

        // Prefetch audio without playing (returns ArrayBuffer)
        async prefetchAudio(text, voiceProvider, voiceId) {
            const settings = Alpine.store('settings');

            let apiKey;
            if (voiceProvider === 'openai_tts') {
                apiKey = settings.openaiKey;
            } else {
                apiKey = settings.elevenlabsKey;
            }

            if (!apiKey || !voiceId) return null;

            const ttsText = text.length > 500 ? text.substring(0, 500) + '...' : text;

            try {
                const requestBody = {
                    action: 'tts',
                    tts_provider: voiceProvider,
                    api_key: apiKey,
                    voice_id: voiceId,
                    text: ttsText,
                };

                if (voiceProvider === 'openai_tts') {
                    requestBody.speed = settings.speechRate || 1.0;
                }

                const response = await fetch('/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(requestBody),
                });

                if (!response.ok) {
                    console.error('TTS prefetch error:', response.status);
                    return null;
                }

                return await response.arrayBuffer();
            } catch (err) {
                console.error('TTS prefetch error:', err);
                return null;
            }
        },

        // Play pre-fetched audio with animation
        async playPrefetched(audioData, agentId) {
            if (!audioData) return;
            await this._playWithAnimation(audioData, agentId);
        },

        // Preview OpenAI voice sample
        async previewVoice(voiceId) {
            const settings = Alpine.store('settings');
            const apiKey = settings.openaiKey;

            if (!apiKey) {
                Alpine.store('ui').notify('Спочатку додайте OpenAI API ключ в Налаштуваннях', 'error');
                return;
            }

            if (!voiceId) {
                Alpine.store('ui').notify('Спочатку оберіть голос', 'error');
                return;
            }

            const previewText = settings.voicePreviewText || 'Привіт! Це приклад мого голосу. Як тобі?';

            try {
                Alpine.store('ui').notify('Завантаження голосу...');
                await this.speak(previewText, 'openai_tts', voiceId, 'preview');
            } catch (err) {
                console.error('Preview error:', err);
                Alpine.store('ui').notify('Не вдалося відтворити голос', 'error');
            }
        },

        // Preview ElevenLabs voice sample
        async previewVoiceElevenlabs(voiceId) {
            const settings = Alpine.store('settings');
            const apiKey = settings.elevenlabsKey;

            if (!apiKey) {
                Alpine.store('ui').notify('Спочатку додайте ElevenLabs API ключ в Налаштуваннях', 'error');
                return;
            }

            if (!voiceId) {
                Alpine.store('ui').notify('Спочатку введіть Voice ID', 'error');
                return;
            }

            const previewText = settings.voicePreviewText || 'Привіт! Це приклад мого голосу. Як тобі?';

            try {
                Alpine.store('ui').notify('Завантаження голосу...');
                await this.speak(previewText, 'elevenlabs', voiceId, 'preview');
            } catch (err) {
                console.error('Preview error:', err);
                Alpine.store('ui').notify('Не вдалося відтворити голос', 'error');
            }
        },

        // Get mouth openness for a specific agent (used in template)
        getMouth(agentId) {
            if (this.speakingAgentId === agentId && this.isSpeaking) {
                return this.mouthOpenness;
            }
            return 0;
        },
    });
});
