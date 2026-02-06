document.addEventListener('alpine:init', () => {
    // Welcome store - initialize first to avoid race conditions
    Alpine.store('welcome', {
        show: !localStorage.getItem('ait_welcome_seen'),

        close() {
            this.show = false;
            localStorage.setItem('ait_welcome_seen', '1');
        },

        openSettings() {
            this.close();
            Alpine.store('settings').open();
        },
    });

    // Settings store
    Alpine.store('settings', {
        show: false,
        openaiKey: '',
        claudeKey: '',
        geminiKey: '',
        elevenlabsKey: '',
        turnMode: 'round-robin',
        contextSize: 20,
        messageLengthLevel: 5,
        speechRate: 1.0,
        voicePreviewText: 'Привіт! Це приклад мого голосу. Як тобі?',
        _snapshot: null,

        init() {
            const saved = Storage.loadSettings();
            Object.assign(this, saved);
            // Default value for new setting
            if (!this.voicePreviewText) {
                this.voicePreviewText = 'Привіт! Це приклад мого голосу. Як тобі?';
            }
        },

        open() {
            this._snapshot = {
                openaiKey: this.openaiKey,
                claudeKey: this.claudeKey,
                geminiKey: this.geminiKey,
                elevenlabsKey: this.elevenlabsKey,
                turnMode: this.turnMode,
                contextSize: this.contextSize,
                messageLengthLevel: this.messageLengthLevel,
                speechRate: this.speechRate,
                voicePreviewText: this.voicePreviewText,
            };
            this.show = true;
        },

        cancel() {
            if (this._snapshot) {
                Object.assign(this, this._snapshot);
            }
            this.show = false;
        },

        save() {
            Storage.saveSettings({
                openaiKey: this.openaiKey,
                claudeKey: this.claudeKey,
                geminiKey: this.geminiKey,
                elevenlabsKey: this.elevenlabsKey,
                turnMode: this.turnMode,
                contextSize: this.contextSize,
                messageLengthLevel: this.messageLengthLevel,
                speechRate: this.speechRate,
                voicePreviewText: this.voicePreviewText,
            });
            this._snapshot = null;
            this.show = false;
        },

        getKeyForProvider(provider) {
            const map = {
                openai: this.openaiKey,
                claude: this.claudeKey,
                gemini: this.geminiKey,
            };
            return map[provider] || '';
        },

        getMessageLengthLabel() {
            const labels = {
                1: '(2-5 слів)',
                2: '(5-10 слів)',
                3: '(10-15 слів)',
                4: '(1-2 речення)',
                5: '(2-3 речення)',
                6: '(1-2 абзаци)',
                7: '(2-3 абзаци)',
                8: '(3-4 абзаци)',
                9: '(детально)',
                10: '(дуже детально)',
            };
            return labels[this.messageLengthLevel] || '';
        },
    });

    // UI store
    Alpine.store('ui', {
        activeTab: 'agents',
        sidebarOpen: true,
        notification: '',
        notificationType: 'info',

        notify(message, type = 'info') {
            this.notification = message;
            this.notificationType = type;
            setTimeout(() => { this.notification = ''; }, 3000);
        },
    });

});
