# AI Talks

**Live Demo:** [https://aitalks.kaplia.pro/](https://aitalks.kaplia.pro/)

A web application where AI agents have autonomous conversations with each other, complete with text-to-speech voices and animated emoji avatars.

## Features

- **Multi-Agent Conversations**: Create multiple AI characters with unique personalities and watch them discuss any topic
- **Multiple LLM Providers**: Each agent can use a different AI provider:
  - OpenAI (GPT-4o, GPT-4o-mini, GPT-4 Turbo, GPT-3.5 Turbo)
  - Anthropic Claude (Claude Sonnet 4, Claude 3.5 Sonnet, Claude 3 Haiku)
  - Google Gemini (Gemini 2.0 Flash, Gemini 1.5 Pro, Gemini 1.5 Flash)
- **Text-to-Speech**: Voices powered by OpenAI TTS or ElevenLabs
- **Animated Avatars**: Emoji faces with expressive emotions:
  - Mouths sync to audio amplitude during speech
  - Eyebrows and expressions change based on conversation context (happy, angry, surprised, thinking)
- **Conversation Controls**: Start, pause, resume, stop conversations at any time
- **User Intervention**: Jump into the conversation with your own messages (agents will react to your input)
- **Agent Temperature**: Control creativity level per agent (0.1 = precise, 1.5 = creative)
- **Emotional Intelligence**: Agents detect agreement, criticism, and debate tension
- **Mobile Responsive**: Works on phones and tablets with collapsible sidebar
- **Welcome Guide**: First-time users see a helpful onboarding modal
- **Save/Load**: Save conversations and agent presets for later use
- **Configurable Settings**:
  - Turn mode: round-robin or random speaker selection
  - Context size: how many messages agents remember
  - Message length: from very short (2-5 words) to detailed paragraphs
  - Speech rate: 0.5x to 2.0x speed (OpenAI TTS)
  - Custom voice preview phrase

## Requirements

- PHP 7.4+ with cURL extension
- Web server (Apache, Nginx, or PHP built-in server)
- API keys for at least one LLM provider
- Optional: OpenAI or ElevenLabs API key for TTS

## Installation

1. Clone or download this repository to your web server directory:
   ```bash
   git clone https://github.com/yourusername/aitalks.git
   cd aitalks
   ```

2. Ensure your web server is configured to serve PHP files

3. Open the application in your browser

4. Click the settings icon (gear) and add your API keys

## Usage

### Setting Up Agents

1. In the left sidebar, click **+ Add** to create agents
2. For each agent, configure:
   - **Name**: The agent's display name
   - **Description**: Personality, speaking style, background (this becomes the system prompt)
   - **Color**: Avatar color
   - **Voice Provider**: OpenAI TTS or ElevenLabs
   - **Voice**: Select from available voices or enter ElevenLabs Voice ID
   - **LLM Provider**: OpenAI, Claude, or Gemini
   - **Model**: Specific model to use
   - **Temperature**: Creativity level (lower = more focused, higher = more creative)
3. Enable/disable agents with the checkbox
4. Save agent configurations as presets for reuse

### Starting a Conversation

1. Enter a topic in the text field at the bottom
2. Click **Start** to begin the conversation
3. Use **Pause** to temporarily stop, **Resume** to continue
4. Click **Intervene** to add your own message to the conversation
5. Click **Stop** to end the conversation

### Saving Conversations

- Click **Save** to store the current conversation
- Click **Load** to restore a previously saved conversation
- Saved conversations include the topic, all messages, and agent configurations

## Tech Stack

- **Backend**: PHP (API proxy for LLM and TTS services)
- **Frontend**:
  - Alpine.js for reactivity
  - Tailwind CSS for styling
  - Web Audio API for audio analysis and mouth animation
- **Storage**: localStorage for all data persistence

## File Structure

```
/index.php          - Main SPA entry point
/api.php            - PHP proxy for API calls
/js/
  storage.js        - localStorage wrapper
  app.js            - Settings and UI stores
  agents.js         - Agent management
  conversation.js   - Conversation engine
  voice.js          - TTS and audio animation
```

## API Proxy

The application uses a PHP backend (`api.php`) to proxy all API calls. This:
- Solves CORS issues (especially for Anthropic Claude API)
- Keeps API key handling on the server side during requests
- Provides a unified interface for multiple providers

### Endpoints

**POST /api.php**

Chat completion:
```json
{
  "action": "chat",
  "provider": "openai|claude|gemini",
  "api_key": "your-api-key",
  "model": "model-name",
  "messages": [...],
  "length_level": 5,
  "temperature": 0.9
}
```

Text-to-speech:
```json
{
  "action": "tts",
  "tts_provider": "openai_tts|elevenlabs",
  "api_key": "your-api-key",
  "voice_id": "voice-id",
  "text": "Text to speak",
  "speed": 1.0
}
```

## Configuration Options

| Setting | Description | Default |
|---------|-------------|---------|
| Turn Mode | How agents take turns (round-robin/random) | round-robin |
| Context Size | Number of recent messages in context | 20 |
| Message Length | Response length level (1-10) | 5 |
| Speech Rate | TTS playback speed (OpenAI only) | 1.0x |
| Voice Preview Text | Custom phrase for voice preview | Ukrainian greeting |
| Agent Temperature | Per-agent creativity (0.1-1.5) | 0.9 |

## License

MIT License

## Contributing

Contributions are welcome! Please feel free to submit issues and pull requests.
