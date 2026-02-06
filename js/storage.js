const Storage = {
    save(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify(data));
            return true;
        } catch (e) {
            console.error('Storage save error:', e);
            return false;
        }
    },

    load(key, defaultValue = null) {
        try {
            const data = localStorage.getItem(key);
            return data ? JSON.parse(data) : defaultValue;
        } catch (e) {
            console.error('Storage load error:', e);
            return defaultValue;
        }
    },

    remove(key) {
        localStorage.removeItem(key);
    },

    list(prefix) {
        const keys = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith(prefix)) {
                keys.push(key);
            }
        }
        return keys;
    },

    // Specific helpers
    saveSettings(settings) {
        this.save('ait_settings', settings);
    },

    loadSettings() {
        return this.load('ait_settings', {
            openaiKey: '',
            claudeKey: '',
            geminiKey: '',
            elevenlabsKey: '',
            turnMode: 'round-robin',
            contextSize: 20,
            messageLengthLevel: 5,
            speechRate: 1.0,
        });
    },

    saveAgents(agents) {
        this.save('ait_agents', agents);
    },

    loadAgents() {
        return this.load('ait_agents', []);
    },

    savePreset(name, agents) {
        this.save('ait_preset_' + name, agents);
    },

    loadPreset(name) {
        return this.load('ait_preset_' + name, []);
    },

    deletePreset(name) {
        this.remove('ait_preset_' + name);
    },

    listPresets() {
        return this.list('ait_preset_').map(key => key.replace('ait_preset_', ''));
    },

    saveConversation(name, data) {
        this.save('ait_conv_' + name, data);
    },

    loadConversation(name) {
        return this.load('ait_conv_' + name, null);
    },

    deleteConversation(name) {
        this.remove('ait_conv_' + name);
    },

    listConversations() {
        return this.list('ait_conv_').map(key => key.replace('ait_conv_', ''));
    },
};
