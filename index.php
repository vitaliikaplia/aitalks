<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Talks — Автономні розмови AI-агентів з озвучкою</title>
    <meta name="description" content="Створюйте AI-персонажів з унікальними голосами та спостерігайте за їхніми автономними бесідами. Підтримка OpenAI, Claude, Gemini та озвучка через OpenAI TTS і ElevenLabs.">
    <meta name="keywords" content="AI, штучний інтелект, чат-боти, OpenAI, Claude, Gemini, TTS, озвучка, ElevenLabs, розмова агентів">
    <meta name="author" content="Vitalii Kaplia">
    <meta property="og:title" content="AI Talks — Автономні розмови AI-агентів">
    <meta property="og:description" content="Створюйте AI-персонажів з унікальними голосами та спостерігайте за їхніми автономними бесідами на будь-яку тему.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://aitalks.kaplia.pro/">
    <meta property="og:image" content="https://aitalks.kaplia.pro/favicon.svg">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="AI Talks — Автономні розмови AI-агентів">
    <meta name="twitter:description" content="Створюйте AI-персонажів з унікальними голосами та спостерігайте за їхніми автономними бесідами.">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="canonical" href="https://aitalks.kaplia.pro/">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="/js/storage.js"></script>
    <script src="/js/app.js"></script>
    <script src="/js/agents.js"></script>
    <script src="/js/voice.js"></script>
    <script src="/js/conversation.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: #1e293b; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 h-screen flex flex-col overflow-hidden" x-data>

    <!-- Notification -->
    <div x-show="$store.ui.notification" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:leave="transition ease-in duration-150"
         class="fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-sm"
         :class="$store.ui.notificationType === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
         x-text="$store.ui.notification">
    </div>

    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
            <button @click="$store.ui.sidebarOpen = !$store.ui.sidebarOpen"
                    class="p-2 hover:bg-gray-700 rounded-lg transition md:hidden" title="Меню">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <h1 class="text-xl font-bold flex items-center gap-2">
                <span class="text-2xl">&#127917;</span> <span class="hidden sm:inline">AI Talks</span>
            </h1>
        </div>
        <button @click="$store.settings.open()"
                class="p-2 hover:bg-gray-700 rounded-lg transition" title="Налаштування">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </button>
    </header>

    <!-- Main content -->
    <div class="flex flex-1 overflow-hidden relative">

        <!-- Mobile sidebar overlay -->
        <div x-show="$store.ui.sidebarOpen"
             x-cloak
             @click="$store.ui.sidebarOpen = false"
             class="fixed inset-0 bg-black/50 z-20 md:hidden"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <!-- Sidebar: Agents -->
        <aside class="bg-gray-800 border-r border-gray-700 flex flex-col overflow-hidden
                      w-72 md:w-80 flex-shrink-0
                      fixed md:static top-14 bottom-0 left-0 z-30
                      transform transition-transform duration-200 ease-in-out md:transform-none"
               :class="$store.ui.sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               x-init="if (window.innerWidth < 768) $store.ui.sidebarOpen = false; window.addEventListener('resize', () => { if (window.innerWidth >= 768) $store.ui.sidebarOpen = true; })">
            <div class="p-3 border-b border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-sm uppercase tracking-wide text-gray-400">Агенти</h2>
                <button @click="$store.agents.add()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 px-2 py-1 rounded transition">
                    + Додати
                </button>
            </div>

            <div class="flex-1 overflow-y-auto scrollbar-thin p-2 space-y-2">
                <template x-for="(agent, index) in $store.agents.list" :key="agent.id">
                    <div class="bg-gray-800 rounded-lg border border-gray-600"
                         :class="{ 'border-blue-500': $store.agents.editingIndex === index }">

                        <!-- Agent header -->
                        <div class="flex items-center gap-2 p-2 cursor-pointer hover:bg-gray-700 rounded-t-lg"
                             :class="{ 'opacity-50': !agent.enabled }"
                             @click="$store.agents.toggleEdit(index)">
                            <!-- Enable/Disable checkbox -->
                            <input type="checkbox" x-model="agent.enabled" @change="$store.agents.persist()"
                                   @click.stop
                                   class="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                                   :title="agent.enabled ? 'Увімкнено' : 'Вимкнено'">
                            <!-- Agent face -->
                            <div class="w-8 h-8 rounded-full flex items-center justify-center relative flex-shrink-0"
                                 :style="'background-color: ' + agent.color">
                                <!-- Eyes -->
                                <div class="absolute" style="top: 8px; left: 7px; width: 5px; height: 5px; background: white; border-radius: 50%;"></div>
                                <div class="absolute" style="top: 8px; right: 7px; width: 5px; height: 5px; background: white; border-radius: 50%;"></div>
                                <!-- Mouth -->
                                <div class="absolute bg-white rounded-full"
                                     style="bottom: 7px; left: 50%; transform: translateX(-50%); width: 10px; height: 4px;"></div>
                            </div>
                            <span class="text-sm font-medium truncate flex-1"
                                  x-text="agent.name || 'Без імені'"></span>
                            <span class="text-xs text-gray-400" x-text="agent.provider"></span>
                        </div>

                        <!-- Agent edit form -->
                        <div x-show="$store.agents.editingIndex === index" x-cloak
                             class="p-3 border-t border-gray-600 space-y-2">

                            <div>
                                <label class="text-xs text-gray-400">Ім'я</label>
                                <input type="text" x-model="agent.name" @input="$store.agents.persist()"
                                       class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm focus:border-blue-500 focus:outline-none"
                                       placeholder="Ім'я агента">
                            </div>

                            <div>
                                <label class="text-xs text-gray-400">Опис персонажа</label>
                                <textarea x-model="agent.description" @input="$store.agents.persist()"
                                          class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm focus:border-blue-500 focus:outline-none resize-none"
                                          rows="3" placeholder="Опишіть характер, стиль спілкування тощо"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-400">Колір</label>
                                    <input type="color" x-model="agent.color" @input="$store.agents.persist()"
                                           class="w-full h-8 bg-gray-700 border border-gray-600 rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Провайдер голосу</label>
                                    <select x-model="agent.voiceProvider"
                                            @change="agent.voiceId = agent.voiceProvider === 'openai_tts' ? 'alloy' : ''; $store.agents.persist()"
                                            class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm focus:border-blue-500 focus:outline-none">
                                        <option value="openai_tts">OpenAI TTS</option>
                                        <option value="elevenlabs">ElevenLabs</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-400" x-text="agent.voiceProvider === 'openai_tts' ? 'Голос OpenAI' : 'ElevenLabs Voice ID'"></label>
                                <template x-if="agent.voiceProvider === 'openai_tts'">
                                    <div class="flex gap-1">
                                        <select x-model="agent.voiceId" @change="$store.agents.persist()"
                                                class="flex-1 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm focus:border-blue-500 focus:outline-none">
                                            <template x-for="v in $store.agents.openaiVoices()" :key="v.value">
                                                <option :value="v.value" x-text="v.label"></option>
                                            </template>
                                        </select>
                                        <button @click="$store.voice.previewVoice(agent.voiceId)"
                                                class="bg-blue-600 hover:bg-blue-700 px-2 py-1 rounded text-xs transition flex items-center gap-1"
                                                :class="{ 'opacity-50 cursor-wait': $store.voice.isLoadingPreview }"
                                                :disabled="$store.voice.isLoadingPreview"
                                                title="Прослухати голос">
                                            <template x-if="!$store.voice.isLoadingPreview">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 3.5a.75.75 0 00-1.264-.546L5.203 6H2.667a.75.75 0 00-.75.75v6.5c0 .414.336.75.75.75h2.536l3.533 3.046A.75.75 0 0010 16.5v-13zm5.95 1.3a.75.75 0 011.06.04 10.04 10.04 0 010 10.32.75.75 0 11-1.1-1.02 8.54 8.54 0 000-8.28.75.75 0 01.04-1.06zm-2.829 2.828a.75.75 0 011.061.039 6.003 6.003 0 010 5.666.75.75 0 11-1.1-1.02 4.503 4.503 0 000-4.626.75.75 0 01.039-1.06z"/>
                                                </svg>
                                            </template>
                                            <template x-if="$store.voice.isLoadingPreview">
                                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </template>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="agent.voiceProvider === 'elevenlabs'">
                                    <div class="flex gap-1">
                                        <input type="text" x-model="agent.voiceId" @input="$store.agents.persist()"
                                               class="flex-1 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm focus:border-blue-500 focus:outline-none"
                                               placeholder="напр. TEyBWD5tAHAW...">
                                        <button @click="$store.voice.previewVoiceElevenlabs(agent.voiceId)"
                                                class="bg-purple-600 hover:bg-purple-700 px-2 py-1 rounded text-xs transition flex items-center gap-1"
                                                title="Прослухати голос"
                                                :disabled="!agent.voiceId || $store.voice.isLoadingPreview"
                                                :class="{ 'opacity-50': !agent.voiceId || $store.voice.isLoadingPreview, 'cursor-not-allowed': !agent.voiceId, 'cursor-wait': $store.voice.isLoadingPreview }">
                                            <template x-if="!$store.voice.isLoadingPreview">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 3.5a.75.75 0 00-1.264-.546L5.203 6H2.667a.75.75 0 00-.75.75v6.5c0 .414.336.75.75.75h2.536l3.533 3.046A.75.75 0 0010 16.5v-13zm5.95 1.3a.75.75 0 011.06.04 10.04 10.04 0 010 10.32.75.75 0 11-1.1-1.02 8.54 8.54 0 000-8.28.75.75 0 01.04-1.06zm-2.829 2.828a.75.75 0 011.061.039 6.003 6.003 0 010 5.666.75.75 0 11-1.1-1.02 4.503 4.503 0 000-4.626.75.75 0 01.039-1.06z"/>
                                                </svg>
                                            </template>
                                            <template x-if="$store.voice.isLoadingPreview">
                                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </template>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-400">LLM Провайдер</label>
                                    <select x-model="agent.provider"
                                            @change="agent.model = $store.agents.modelsForProvider(agent.provider)[0]?.value || ''; $store.agents.persist()"
                                            class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm focus:border-blue-500 focus:outline-none">
                                        <option value="openai">OpenAI</option>
                                        <option value="claude">Claude</option>
                                        <option value="gemini">Gemini</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Модель</label>
                                    <select x-model="agent.model" @change="$store.agents.persist()"
                                            class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm focus:border-blue-500 focus:outline-none">
                                        <template x-for="m in $store.agents.modelsForProvider(agent.provider)" :key="m.value">
                                            <option :value="m.value" x-text="m.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-400">Температура (креативність): <span x-text="agent.temperature ?? 0.9"></span></label>
                                <input type="range" x-model="agent.temperature" @input="$store.agents.persist()"
                                       min="0.1" max="1.5" step="0.1"
                                       class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                                <div class="flex justify-between text-xs text-gray-500 mt-0.5">
                                    <span>Точніше</span>
                                    <span>Креативніше</span>
                                </div>
                            </div>

                            <div class="flex gap-1 pt-1">
                                <button @click="$store.agents.duplicate(index)"
                                        class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded transition">
                                    Дублювати
                                </button>
                                <button @click="$store.agents.remove(index)"
                                        class="text-xs bg-red-600/50 hover:bg-red-600 px-2 py-1 rounded transition"
                                        x-show="$store.agents.list.length > 1">
                                    Видалити
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Presets -->
            <div class="p-3 border-t border-gray-700 space-y-2">
                <h3 class="text-xs text-gray-400 uppercase tracking-wide">Пресети</h3>
                <div class="flex gap-1">
                    <input type="text" x-model="$store.agents.presetName"
                           class="flex-1 bg-gray-700 border border-gray-600 rounded px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
                           placeholder="Назва пресету" @keydown.enter="$store.agents.savePreset()">
                    <button @click="$store.agents.savePreset()"
                            class="text-xs bg-emerald-600 hover:bg-emerald-700 px-2 py-1 rounded transition">Зберегти</button>
                </div>
                <template x-if="$store.agents.presets.length > 0">
                    <div class="space-y-1 max-h-24 overflow-y-auto scrollbar-thin">
                        <template x-for="preset in $store.agents.presets" :key="preset">
                            <div class="flex items-center justify-between bg-gray-700 rounded px-2 py-1">
                                <button @click="$store.agents.loadPreset(preset)"
                                        class="text-xs text-blue-400 hover:text-blue-300 truncate flex-1 text-left"
                                        x-text="preset"></button>
                                <button @click="$store.agents.deletePreset(preset)"
                                        class="text-xs text-red-400 hover:text-red-300 ml-1">&times;</button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </aside>

        <!-- Chat area -->
        <main class="flex-1 flex flex-col overflow-hidden">

            <!-- Agent avatars bar -->
            <div class="bg-gray-800/50 border-b border-gray-700 px-4 py-2 flex items-center gap-3 flex-shrink-0"
                 x-show="$store.agents.list.some(a => a.enabled && a.name.trim())">
                <template x-for="agent in $store.agents.list.filter(a => a.enabled && a.name.trim())" :key="agent.id">
                    <div class="flex flex-col items-center gap-1"
                         x-data="{ get emotion() { return $store.conversation.getAgentEmotion(agent.id) } }">
                        <!-- Animated face -->
                        <svg width="48" height="48" viewBox="0 0 48 48"
                             :class="{ 'ring-2 ring-yellow-400 rounded-full': $store.voice.speakingAgentId === agent.id }"
                             class="transition-all duration-300">
                            <!-- Head -->
                            <circle cx="24" cy="24" r="22" :fill="agent.color" />

                            <!-- Left eyebrow -->
                            <line x1="12" x2="20"
                                  :y1="emotion === 'angry' ? 11 : (emotion === 'surprised' ? 8 : 10)"
                                  :y2="emotion === 'angry' ? 9 : (emotion === 'surprised' ? 8 : 10)"
                                  stroke="#333" stroke-width="2" stroke-linecap="round"
                                  :transform="emotion === 'thinking' ? 'rotate(-10, 16, 10)' : ''"
                                  class="transition-all duration-300" />
                            <!-- Right eyebrow -->
                            <line x1="28" x2="36"
                                  :y1="emotion === 'angry' ? 9 : (emotion === 'surprised' ? 8 : 10)"
                                  :y2="emotion === 'angry' ? 11 : (emotion === 'surprised' ? 8 : 10)"
                                  stroke="#333" stroke-width="2" stroke-linecap="round"
                                  :transform="emotion === 'thinking' ? 'rotate(10, 32, 10)' : ''"
                                  class="transition-all duration-300" />

                            <!-- Left eye -->
                            <ellipse cx="16" cy="18"
                                     :rx="emotion === 'happy' ? 3 : (emotion === 'surprised' ? 3.5 : 3)"
                                     :ry="emotion === 'happy' ? 1.5 : (emotion === 'surprised' ? 4 : 3)"
                                     fill="white" class="transition-all duration-300" />
                            <circle cx="16" cy="18" r="1.5" fill="#333"
                                    :class="{ 'hidden': emotion === 'happy' }" />
                            <!-- Right eye -->
                            <ellipse cx="32" cy="18"
                                     :rx="emotion === 'happy' ? 3 : (emotion === 'surprised' ? 3.5 : 3)"
                                     :ry="emotion === 'happy' ? 1.5 : (emotion === 'surprised' ? 4 : 3)"
                                     fill="white" class="transition-all duration-300" />
                            <circle cx="32" cy="18" r="1.5" fill="#333"
                                    :class="{ 'hidden': emotion === 'happy' }" />

                            <!-- Mouth - different shapes based on emotion and voice -->
                            <!-- Happy mouth (smile arc) -->
                            <path x-show="emotion === 'happy' && $store.voice.getMouth(agent.id) < 0.2"
                                  d="M 16 32 Q 24 40, 32 32" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" />
                            <!-- Angry mouth (frown) -->
                            <path x-show="emotion === 'angry' && $store.voice.getMouth(agent.id) < 0.2"
                                  d="M 16 36 Q 24 30, 32 36" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" />
                            <!-- Surprised mouth (O shape) -->
                            <ellipse x-show="emotion === 'surprised' && $store.voice.getMouth(agent.id) < 0.2"
                                     cx="24" cy="34" rx="4" ry="5" fill="white" />
                            <!-- Thinking mouth (slight line) -->
                            <line x-show="emotion === 'thinking' && $store.voice.getMouth(agent.id) < 0.2"
                                  x1="18" y1="34" x2="30" y2="33" stroke="white" stroke-width="2" stroke-linecap="round" />
                            <!-- Neutral/speaking mouth (animated ellipse) -->
                            <ellipse x-show="emotion === 'neutral' || $store.voice.getMouth(agent.id) >= 0.2"
                                     cx="24" cy="33" rx="6"
                                     :ry="Math.max(1, $store.voice.getMouth(agent.id) * 6)"
                                     fill="white" class="transition-all duration-75" />
                        </svg>
                        <span class="text-xs text-gray-400 truncate max-w-[60px]" x-text="agent.name"></span>
                    </div>
                </template>
            </div>

            <!-- Messages -->
            <div id="chat-messages" class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-3">
                <template x-if="$store.conversation.messages.length === 0">
                    <div class="flex items-center justify-center h-full text-gray-500">
                        <div class="text-center">
                            <p class="text-lg">Налаштуйте агентів, введіть тему і почніть розмову!</p>
                            <p class="text-sm mt-2">Потрібно мінімум 2 агенти з іменами та описами.</p>
                        </div>
                    </div>
                </template>

                <template x-for="(msg, i) in $store.conversation.messages" :key="i">
                    <div class="flex gap-3" :class="msg.agentId === 'user' ? 'justify-end' : ''">
                        <!-- Agent indicator -->
                        <div x-show="msg.agentId !== 'user'" class="flex-shrink-0 mt-1">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                                 :style="'background-color: ' + msg.agentColor"
                                 x-text="(msg.agentName || '?')[0]"></div>
                        </div>

                        <!-- Message bubble -->
                        <div class="max-w-[70%] rounded-lg px-3 py-2"
                             :class="msg.agentId === 'user' ? 'bg-blue-600' : msg.role === 'system' ? 'bg-gray-700 text-gray-400 italic' : 'bg-gray-700'">
                            <div class="flex items-center gap-2 mb-1" x-show="msg.role !== 'system'">
                                <span class="text-xs font-semibold" :style="'color: ' + msg.agentColor"
                                      x-text="msg.agentName"></span>
                                <span class="text-xs text-gray-500"
                                      x-text="new Date(msg.timestamp).toLocaleTimeString()"></span>
                            </div>
                            <p class="text-sm whitespace-pre-wrap" x-text="msg.content"></p>
                        </div>
                    </div>
                </template>

                <!-- Typing indicator -->
                <div x-show="$store.conversation.isGenerating" class="flex gap-3">
                    <div class="flex-shrink-0 mt-1">
                        <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center">
                            <div class="flex gap-1">
                                <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-700 rounded-lg px-3 py-2">
                        <span class="text-sm text-gray-400">думає...</span>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="bg-gray-800 border-t border-gray-700 p-3 space-y-2 flex-shrink-0">
                <!-- Topic input -->
                <div class="flex gap-2" x-show="$store.conversation.state === 'idle'">
                    <input type="text" x-model="$store.conversation.topic"
                           class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                           placeholder="Введіть тему розмови..."
                           @keydown.enter="$store.conversation.start()">
                    <button @click="$store.conversation.start()"
                            class="bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg text-sm font-medium transition"
                            :disabled="!$store.agents.canStart()"
                            :class="{ 'opacity-50 cursor-not-allowed': !$store.agents.canStart() }">
                        &#9654; Старт
                    </button>
                </div>

                <!-- Running controls -->
                <div class="flex gap-2 items-center" x-show="$store.conversation.state !== 'idle'">
                    <button @click="$store.conversation.state === 'running' ? $store.conversation.pause() : $store.conversation.resume()"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition"
                            :class="$store.conversation.state === 'running' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-emerald-600 hover:bg-emerald-700'">
                        <span x-show="$store.conversation.state === 'running'">&#9208; Пауза</span>
                        <span x-show="$store.conversation.state === 'paused'">&#9654; Продовжити</span>
                    </button>

                    <button @click="$store.conversation.showIntervention = !$store.conversation.showIntervention"
                            class="bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded-lg text-sm font-medium transition">
                        &#9995; Втрутитись
                    </button>

                    <button @click="$store.conversation.stop()"
                            class="bg-red-600 hover:bg-red-700 px-3 py-2 rounded-lg text-sm font-medium transition">
                        &#9632; Стоп
                    </button>

                    <button @click="$store.ui.toggleMute()"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition"
                            :class="$store.ui.voiceMuted ? 'bg-gray-600 hover:bg-gray-500' : 'bg-purple-600 hover:bg-purple-700'"
                            :title="$store.ui.voiceMuted ? 'Увімкнути озвучку' : 'Вимкнути озвучку'">
                        <span x-show="!$store.ui.voiceMuted">&#128264;</span>
                        <span x-show="$store.ui.voiceMuted">&#128263;</span>
                    </button>

                    <div class="flex-1"></div>

                    <!-- Save/Load -->
                    <button @click="$store.conversation.showSaveDialog = true"
                            class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded transition">
                        Зберегти
                    </button>
                    <button @click="$store.conversation.showLoadDialog = true"
                            class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded transition">
                        Завантажити
                    </button>
                </div>

                <!-- Intervention input -->
                <div class="flex gap-2" x-show="$store.conversation.showIntervention" x-cloak>
                    <input type="text" x-model="$store.conversation.interventionText"
                           class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                           placeholder="Напишіть своє повідомлення..."
                           @keydown.enter="$store.conversation.intervene()">
                    <button @click="$store.conversation.intervene()"
                            class="bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded-lg text-sm transition">
                        Надіслати
                    </button>
                </div>

                <!-- Idle save/load -->
                <div class="flex gap-2" x-show="$store.conversation.state === 'idle' && $store.conversation.messages.length > 0">
                    <button @click="$store.conversation.showSaveDialog = true"
                            class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded transition">
                        Зберегти розмову
                    </button>
                    <button @click="$store.conversation.showLoadDialog = true"
                            class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded transition">
                        Завантажити розмову
                    </button>
                </div>
                <div x-show="$store.conversation.state === 'idle' && $store.conversation.messages.length === 0">
                    <button @click="$store.conversation.showLoadDialog = true"
                            class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded transition"
                            x-show="$store.conversation.savedConversations.length > 0">
                        Завантажити розмову
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Settings Modal -->
    <div x-show="$store.settings.show" x-cloak
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-40"
         @click.self="$store.settings.cancel()">
        <div class="bg-gray-800 border border-gray-600 rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-4">
            <h2 class="text-lg font-bold">Налаштування</h2>

            <div class="space-y-3">
                <div>
                    <label class="text-xs text-gray-400 block mb-1">OpenAI API ключ</label>
                    <input type="password" x-model="$store.settings.openaiKey"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                           placeholder="sk-...">
                </div>
                <div>
                    <label class="text-xs text-gray-400 block mb-1">Anthropic (Claude) API ключ</label>
                    <input type="password" x-model="$store.settings.claudeKey"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                           placeholder="sk-ant-...">
                </div>
                <div>
                    <label class="text-xs text-gray-400 block mb-1">Google Gemini API ключ</label>
                    <input type="password" x-model="$store.settings.geminiKey"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                           placeholder="AI...">
                </div>
                <div>
                    <label class="text-xs text-gray-400 block mb-1">ElevenLabs API ключ</label>
                    <input type="password" x-model="$store.settings.elevenlabsKey"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                           placeholder="xi-...">
                </div>

                <hr class="border-gray-600">

                <div>
                    <label class="text-xs text-gray-400 block mb-1">Режим черги</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" x-model="$store.settings.turnMode" value="round-robin"
                                   class="text-blue-500">
                            По черзі
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" x-model="$store.settings.turnMode" value="random"
                                   class="text-blue-500">
                            Випадково
                        </label>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-400 block mb-1">
                        Розмір контексту (останніх повідомлень): <span x-text="$store.settings.contextSize" class="text-blue-400"></span>
                    </label>
                    <input type="range" x-model.number="$store.settings.contextSize"
                           min="5" max="50" step="5"
                           class="w-full">
                </div>

                <div>
                    <label class="text-xs text-gray-400 block mb-1">
                        Довжина повідомлень: <span x-text="$store.settings.messageLengthLevel" class="text-blue-400"></span>
                        <span class="text-gray-500 text-xs ml-1" x-text="$store.settings.getMessageLengthLabel()"></span>
                    </label>
                    <input type="range" x-model.number="$store.settings.messageLengthLevel"
                           min="1" max="10" step="1"
                           class="w-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>Дуже коротко</span>
                        <span>Довго</span>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-400 block mb-1">
                        Швидкість мовлення: <span x-text="parseFloat($store.settings.speechRate).toFixed(1) + 'x'" class="text-blue-400"></span>
                        <span class="text-gray-500 text-xs ml-1">(тільки OpenAI TTS)</span>
                    </label>
                    <input type="range" x-model.number="$store.settings.speechRate"
                           min="0.5" max="2.0" step="0.1"
                           class="w-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>0.5x (Повільно)</span>
                        <span>2.0x (Швидко)</span>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-400 block mb-1">Фраза для перевірки голосу</label>
                    <input type="text" x-model="$store.settings.voicePreviewText"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                           placeholder="Привіт! Це приклад мого голосу.">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button @click="$store.settings.cancel()"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg text-sm transition">Скасувати</button>
                <button @click="$store.settings.save()"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm transition">Зберегти</button>
            </div>
        </div>
    </div>

    <!-- Save Conversation Modal -->
    <div x-show="$store.conversation.showSaveDialog" x-cloak
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-40"
         @click.self="$store.conversation.showSaveDialog = false">
        <div class="bg-gray-800 border border-gray-600 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h2 class="text-lg font-bold">Зберегти розмову</h2>
            <input type="text" x-model="$store.conversation.saveDialogName"
                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                   placeholder="Назва розмови"
                   @keydown.enter="$store.conversation.saveConversation()">
            <div class="flex justify-end gap-2">
                <button @click="$store.conversation.showSaveDialog = false"
                        class="px-3 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg text-sm transition">Скасувати</button>
                <button @click="$store.conversation.saveConversation()"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 rounded-lg text-sm transition">Зберегти</button>
            </div>
        </div>
    </div>

    <!-- Load Conversation Modal -->
    <div x-show="$store.conversation.showLoadDialog" x-cloak
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-40"
         @click.self="$store.conversation.showLoadDialog = false">
        <div class="bg-gray-800 border border-gray-600 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h2 class="text-lg font-bold">Завантажити розмову</h2>
            <template x-if="$store.conversation.savedConversations.length === 0">
                <p class="text-sm text-gray-400">Немає збережених розмов.</p>
            </template>
            <div class="space-y-2 max-h-60 overflow-y-auto scrollbar-thin">
                <template x-for="name in $store.conversation.savedConversations" :key="name">
                    <div class="flex items-center justify-between bg-gray-700 rounded px-3 py-2">
                        <button @click="$store.conversation.loadConversation(name)"
                                class="text-sm text-blue-400 hover:text-blue-300 truncate flex-1 text-left"
                                x-text="name"></button>
                        <button @click="$store.conversation.deleteConversation(name)"
                                class="text-xs text-red-400 hover:text-red-300 ml-2">&times;</button>
                    </div>
                </template>
            </div>
            <div class="flex justify-end">
                <button @click="$store.conversation.showLoadDialog = false"
                        class="px-3 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg text-sm transition">Закрити</button>
            </div>
        </div>
    </div>

    <!-- Welcome Modal -->
    <div x-show="$store.welcome.show" x-cloak
         class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="bg-gray-800 border border-gray-600 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <!-- Header with emoji -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6 text-center">
                <div class="text-5xl mb-3">&#127917;</div>
                <h2 class="text-2xl font-bold">Ласкаво просимо до AI Talks!</h2>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto scrollbar-thin">
                <p class="text-gray-300">
                    <strong>AI Talks</strong> — це унікальний додаток, де AI-агенти спілкуються між собою автономно,
                    з реалістичними голосами та анімованими аватарами.
                </p>

                <div class="bg-gray-700/50 rounded-lg p-4 space-y-3">
                    <h3 class="font-semibold text-blue-400 flex items-center gap-2">
                        <span class="text-xl">&#129504;</span> Мозок агентів (LLM)
                    </h3>
                    <p class="text-sm text-gray-300">
                        Для генерації діалогів потрібен API ключ від одного з провайдерів:
                    </p>
                    <ul class="text-sm text-gray-400 space-y-1 ml-4">
                        <li><strong class="text-green-400">OpenAI</strong> — GPT-4o, GPT-4o-mini та інші.
                            <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-400 hover:underline">Отримати ключ</a>
                        </li>
                        <li><strong class="text-orange-400">Anthropic Claude</strong> — Claude Sonnet 4, Claude 3.5.
                            <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-blue-400 hover:underline">Отримати ключ</a>
                        </li>
                        <li><strong class="text-blue-400">Google Gemini</strong> — Gemini 2.0 Flash, Gemini 1.5 Pro.
                            <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-blue-400 hover:underline">Отримати ключ</a>
                        </li>
                    </ul>
                </div>

                <div class="bg-gray-700/50 rounded-lg p-4 space-y-3">
                    <h3 class="font-semibold text-purple-400 flex items-center gap-2">
                        <span class="text-xl">&#127908;</span> Голос агентів (TTS)
                    </h3>
                    <p class="text-sm text-gray-300">
                        Для озвучування діалогів можна використати:
                    </p>
                    <ul class="text-sm text-gray-400 space-y-1 ml-4">
                        <li><strong class="text-green-400">OpenAI TTS</strong> — 13 реалістичних голосів, використовує той самий ключ що й GPT.</li>
                        <li><strong class="text-purple-400">ElevenLabs</strong> — преміум голоси з клонуванням. Потрібен окремий ключ.
                            <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank" class="text-blue-400 hover:underline">Отримати ключ</a>
                        </li>
                    </ul>
                </div>

                <div class="bg-gray-700/50 rounded-lg p-4 space-y-2">
                    <h3 class="font-semibold text-emerald-400 flex items-center gap-2">
                        <span class="text-xl">&#128640;</span> Як почати
                    </h3>
                    <ol class="text-sm text-gray-300 space-y-1 ml-4 list-decimal">
                        <li>Відкрийте <strong>Налаштування</strong> (іконка шестерні) і додайте API ключі</li>
                        <li>Створіть мінімум <strong>2 агенти</strong> з іменами та описом характеру</li>
                        <li>Оберіть голос та модель для кожного агента</li>
                        <li>Введіть тему та натисніть <strong>Старт</strong></li>
                        <li>Насолоджуйтесь автономною розмовою!</li>
                    </ol>
                </div>

                <div class="bg-emerald-900/30 border border-emerald-700/50 rounded-lg p-3 space-y-1">
                    <h3 class="font-semibold text-emerald-400 flex items-center gap-2 text-sm">
                        <span>&#128274;</span> Безпека ваших даних
                    </h3>
                    <p class="text-xs text-gray-400">
                        Всі налаштування та API ключі зберігаються <strong class="text-gray-300">виключно у localStorage вашого браузера</strong>.
                        Ми не маємо серверної бази даних і не збираємо жодних даних. Ключі передаються напряму до офіційних API
                        (OpenAI, Anthropic, Google, ElevenLabs) і нікуди більше. Ви можете безпечно зберігати свої ключі в додатку.
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-700 flex gap-3">
                <button @click="$store.welcome.close()"
                        class="flex-1 px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg text-sm font-medium transition">
                    Закрити
                </button>
                <button @click="$store.welcome.openSettings()"
                        class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Налаштування
                </button>
            </div>
        </div>
    </div>

</body>
</html>
