<?php
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['action'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Missing action']);
    exit;
}

$action = $input['action'];

if ($action === 'chat') {
    handleChat($input);
} elseif ($action === 'tts') {
    handleTTS($input);
} else {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action: ' . $action]);
}

function getMaxTokens($input) {
    $level = intval($input['length_level'] ?? 5);
    $map = [
        1 => 50,   2 => 100,  3 => 150,  4 => 250,
        5 => 400,  6 => 600,  7 => 900,  8 => 1300,
        9 => 1800, 10 => 2500,
    ];
    return $map[$level] ?? 400;
}

function handleChat($input) {
    $provider = $input['provider'] ?? '';
    $apiKey = $input['api_key'] ?? '';
    $model = $input['model'] ?? '';
    $messages = $input['messages'] ?? [];
    $maxTokens = getMaxTokens($input);
    $temperature = floatval($input['temperature'] ?? 0.9);
    // Clamp temperature to valid range
    $temperature = max(0.0, min(2.0, $temperature));

    if (!$apiKey) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Missing API key']);
        return;
    }

    header('Content-Type: application/json');

    switch ($provider) {
        case 'openai':
            chatOpenAI($apiKey, $model, $messages, $maxTokens, $temperature);
            break;
        case 'claude':
            chatClaude($apiKey, $model, $messages, $maxTokens, $temperature);
            break;
        case 'gemini':
            chatGemini($apiKey, $model, $messages, $maxTokens, $temperature);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown provider: ' . $provider]);
    }
}

function chatOpenAI($apiKey, $model, $messages, $maxTokens = 300, $temperature = 0.9) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => $model ?: 'gpt-4o-mini',
        'messages' => $messages,
        'max_tokens' => $maxTokens,
        'temperature' => $temperature,
    ];

    $response = curlPost($url, $data, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);

    if ($response['error']) {
        http_response_code(502);
        echo json_encode(['error' => 'OpenAI API error: ' . $response['error']]);
        return;
    }

    $body = json_decode($response['body'], true);
    if (isset($body['error'])) {
        http_response_code(502);
        echo json_encode(['error' => 'OpenAI: ' . ($body['error']['message'] ?? 'Unknown error')]);
        return;
    }

    $content = $body['choices'][0]['message']['content'] ?? '';
    echo json_encode(['content' => $content]);
}

function chatClaude($apiKey, $model, $messages, $maxTokens = 300, $temperature = 0.9) {
    $url = 'https://api.anthropic.com/v1/messages';

    // Extract system message
    $system = '';
    $claudeMessages = [];
    foreach ($messages as $msg) {
        if ($msg['role'] === 'system') {
            $system = $msg['content'];
        } else {
            // Claude requires alternating user/assistant, merge consecutive same-role
            $role = $msg['role'] === 'assistant' ? 'assistant' : 'user';
            if (count($claudeMessages) > 0 && $claudeMessages[count($claudeMessages) - 1]['role'] === $role) {
                $claudeMessages[count($claudeMessages) - 1]['content'] .= "\n" . $msg['content'];
            } else {
                $claudeMessages[] = ['role' => $role, 'content' => $msg['content']];
            }
        }
    }

    // Ensure first message is from user
    if (count($claudeMessages) > 0 && $claudeMessages[0]['role'] !== 'user') {
        array_unshift($claudeMessages, ['role' => 'user', 'content' => '[Conversation starts]']);
    }

    // Ensure not empty
    if (count($claudeMessages) === 0) {
        $claudeMessages[] = ['role' => 'user', 'content' => 'Start the conversation.'];
    }

    $data = [
        'model' => $model ?: 'claude-sonnet-4-20250514',
        'max_tokens' => $maxTokens,
        'messages' => $claudeMessages,
        'temperature' => $temperature,
    ];
    if ($system) {
        $data['system'] = $system;
    }

    $response = curlPost($url, $data, [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json',
    ]);

    if ($response['error']) {
        http_response_code(502);
        echo json_encode(['error' => 'Claude API error: ' . $response['error']]);
        return;
    }

    $body = json_decode($response['body'], true);
    if (isset($body['error'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Claude: ' . ($body['error']['message'] ?? 'Unknown error')]);
        return;
    }

    $content = '';
    if (isset($body['content'])) {
        foreach ($body['content'] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }
    }
    echo json_encode(['content' => $content]);
}

function chatGemini($apiKey, $model, $messages, $maxTokens = 300, $temperature = 0.9) {
    $model = $model ?: 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

    // Convert to Gemini format
    $systemInstruction = '';
    $contents = [];
    foreach ($messages as $msg) {
        if ($msg['role'] === 'system') {
            $systemInstruction = $msg['content'];
        } else {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            // Merge consecutive same-role messages
            if (count($contents) > 0 && $contents[count($contents) - 1]['role'] === $role) {
                $contents[count($contents) - 1]['parts'][0]['text'] .= "\n" . $msg['content'];
            } else {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }
    }

    // Ensure first message is from user
    if (count($contents) > 0 && $contents[0]['role'] !== 'user') {
        array_unshift($contents, [
            'role' => 'user',
            'parts' => [['text' => '[Conversation starts]']],
        ]);
    }

    if (count($contents) === 0) {
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => 'Start the conversation.']],
        ];
    }

    // Ensure last message is from user (Gemini requirement)
    if ($contents[count($contents) - 1]['role'] !== 'user') {
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => '[Continue the conversation]']],
        ];
    }

    $data = [
        'contents' => $contents,
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'temperature' => $temperature,
        ],
    ];
    if ($systemInstruction) {
        $data['systemInstruction'] = [
            'parts' => [['text' => $systemInstruction]],
        ];
    }

    $response = curlPost($url, $data, [
        'Content-Type: application/json',
    ]);

    if ($response['error']) {
        http_response_code(502);
        echo json_encode(['error' => 'Gemini API error: ' . $response['error']]);
        return;
    }

    $body = json_decode($response['body'], true);
    if (isset($body['error'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Gemini: ' . ($body['error']['message'] ?? 'Unknown error')]);
        return;
    }

    $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo json_encode(['content' => $content]);
}

function handleTTS($input) {
    $ttsProvider = $input['tts_provider'] ?? 'elevenlabs';

    if ($ttsProvider === 'openai_tts') {
        handleTTSOpenAI($input);
    } else {
        handleTTSElevenLabs($input);
    }
}

function handleTTSOpenAI($input) {
    $apiKey = $input['api_key'] ?? '';
    $voice = $input['voice_id'] ?? 'alloy';
    $text = $input['text'] ?? '';
    $speed = $input['speed'] ?? 1.0;

    if (!$apiKey || !$text) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Missing OpenAI TTS parameters']);
        return;
    }

    $url = 'https://api.openai.com/v1/audio/speech';

    $data = [
        'model' => 'gpt-4o-mini-tts',
        'input' => $text,
        'voice' => $voice,
        'speed' => floatval($speed),
        'response_format' => 'mp3',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['error' => 'OpenAI TTS error: ' . $error]);
        return;
    }

    if ($httpCode !== 200) {
        header('Content-Type: application/json');
        http_response_code(502);
        $errBody = json_decode($body, true);
        $errMsg = $errBody['error']['message'] ?? 'HTTP ' . $httpCode;
        echo json_encode(['error' => 'OpenAI TTS: ' . $errMsg]);
        return;
    }

    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($body));
    echo $body;
}

function handleTTSElevenLabs($input) {
    $apiKey = $input['api_key'] ?? '';
    $voiceId = $input['voice_id'] ?? '';
    $text = $input['text'] ?? '';

    if (!$apiKey || !$voiceId || !$text) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Missing ElevenLabs TTS parameters']);
        return;
    }

    $url = 'https://api.elevenlabs.io/v1/text-to-speech/' . urlencode($voiceId);

    $data = [
        'text' => $text,
        'model_id' => 'eleven_multilingual_v2',
        'voice_settings' => [
            'stability' => 0.5,
            'similarity_boost' => 0.75,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'xi-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: audio/mpeg',
        ],
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['error' => 'ElevenLabs error: ' . $error]);
        return;
    }

    if ($httpCode !== 200) {
        header('Content-Type: application/json');
        http_response_code(502);
        $errBody = json_decode($body, true);
        $errMsg = $errBody['detail']['message'] ?? $errBody['detail'] ?? 'HTTP ' . $httpCode;
        echo json_encode(['error' => 'ElevenLabs: ' . $errMsg]);
        return;
    }

    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($body));
    echo $body;
}

function curlPost($url, $data, $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return ['body' => $body, 'error' => $error ?: null];
}
