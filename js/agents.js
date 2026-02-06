document.addEventListener('alpine:init', () => {

    Alpine.store('agents', {
        list: [],
        editingIndex: -1,
        presetName: '',
        presets: [],

        init() {
            this.list = Storage.loadAgents();
            if (this.list.length === 0) {
                this.add();
                this.add();
            }
            this.presets = Storage.listPresets();
        },

        createDefault() {
            return {
                id: Date.now() + Math.random().toString(36).slice(2, 7),
                name: '',
                description: '',
                color: this._randomColor(),
                voiceProvider: 'openai_tts',
                voiceId: 'alloy',
                provider: 'openai',
                model: 'gpt-4o-mini',
                enabled: true,
            };
        },

        openaiVoices() {
            return [
                { value: 'alloy', label: 'Alloy' },
                { value: 'ash', label: 'Ash' },
                { value: 'ballad', label: 'Ballad' },
                { value: 'coral', label: 'Coral' },
                { value: 'echo', label: 'Echo' },
                { value: 'fable', label: 'Fable' },
                { value: 'nova', label: 'Nova' },
                { value: 'onyx', label: 'Onyx' },
                { value: 'sage', label: 'Sage' },
                { value: 'shimmer', label: 'Shimmer' },
                { value: 'verse', label: 'Verse' },
                { value: 'marin', label: 'Marin (recommended)' },
                { value: 'cedar', label: 'Cedar (recommended)' },
            ];
        },

        _randomColor() {
            const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'];
            return colors[Math.floor(Math.random() * colors.length)];
        },

        add() {
            this.list.push(this.createDefault());
            this.persist();
        },

        remove(index) {
            if (this.list.length <= 1) return;
            this.list.splice(index, 1);
            if (this.editingIndex === index) this.editingIndex = -1;
            if (this.editingIndex > index) this.editingIndex--;
            this.persist();
        },

        duplicate(index) {
            const copy = JSON.parse(JSON.stringify(this.list[index]));
            copy.id = Date.now() + Math.random().toString(36).slice(2, 7);
            copy.name = copy.name + ' (copy)';
            this.list.splice(index + 1, 0, copy);
            this.persist();
        },

        toggleEdit(index) {
            this.editingIndex = this.editingIndex === index ? -1 : index;
        },

        persist() {
            Storage.saveAgents(this.list);
        },

        modelsForProvider(provider) {
            const models = {
                openai: [
                    { value: 'gpt-4o', label: 'GPT-4o' },
                    { value: 'gpt-4o-mini', label: 'GPT-4o Mini' },
                    { value: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
                    { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
                ],
                claude: [
                    { value: 'claude-sonnet-4-20250514', label: 'Claude Sonnet 4' },
                    { value: 'claude-3-5-sonnet-20241022', label: 'Claude 3.5 Sonnet' },
                    { value: 'claude-3-haiku-20240307', label: 'Claude 3 Haiku' },
                ],
                gemini: [
                    { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash' },
                    { value: 'gemini-1.5-pro', label: 'Gemini 1.5 Pro' },
                    { value: 'gemini-1.5-flash', label: 'Gemini 1.5 Flash' },
                ],
            };
            return models[provider] || [];
        },

        // Presets
        savePreset() {
            if (!this.presetName.trim()) {
                Alpine.store('ui').notify('Введіть назву пресету', 'error');
                return;
            }
            Storage.savePreset(this.presetName, this.list);
            this.presets = Storage.listPresets();
            Alpine.store('ui').notify('Пресет збережено: ' + this.presetName);
            this.presetName = '';
        },

        loadPreset(name) {
            const agents = Storage.loadPreset(name);
            if (agents.length > 0) {
                this.list = agents;
                this.persist();
                this.editingIndex = -1;
                Alpine.store('ui').notify('Пресет завантажено: ' + name);
            }
        },

        deletePreset(name) {
            Storage.deletePreset(name);
            this.presets = Storage.listPresets();
            Alpine.store('ui').notify('Пресет видалено: ' + name);
        },

        canStart() {
            const validAgents = this.list.filter(a =>
                a.enabled && a.name.trim() && a.description.trim()
            );
            return validAgents.length >= 2;
        },
    });
});
