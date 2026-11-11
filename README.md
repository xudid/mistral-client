# Mistral Client

A small PHP client for Mistral chat completions.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Xudid\MistralClient\Mistral;
use Xudid\MistralClient\Message\AssistantMessage;
use Xudid\MistralClient\Message\SystemMessage;
use Xudid\MistralClient\Message\ToolMessage;
use Xudid\MistralClient\Message\UserMessage;
use Xudid\MistralClient\ResponseFormat;

$client = new Mistral(apiKey: getenv('MISTRAL_API_KEY'));
$response = $client->chat->complete(
    model: 'mistral-large-latest',
    messages: [new UserMessage('Hello')]
);

echo $response['choices'][0]['message']['content'];
```

`complete()` sends a synchronous request to the chat-completions endpoint and returns the full decoded JSON response as an associative array. HTTP and network errors are raised by Guzzle, and invalid JSON responses raise `JsonException`.

Configure a response format to enable text, JSON, or JSON Schema output. The configured format is reused across calls until you replace or clear it:

```php
$client->chat->responseFormat(new ResponseFormat('json_object'));
$response = $client->chat->complete(
    model: 'mistral-large-latest',
    messages: [new UserMessage('Return a short answer as JSON.')]
);

$client->chat->responseFormat(new ResponseFormat('json_schema', [
    'name' => 'result',
    'schema' => ['type' => 'object', 'properties' => ['value' => ['type' => 'string']]],
]));
$response = $client->chat->complete(
    model: 'mistral-large-latest',
    messages: [new UserMessage('Respond using the requested schema.')]
);
$client->chat->responseFormat(null); // Clear the configured format.
```

For JSON mode, prompt the model to produce JSON. The configured `ResponseFormat` is sent as Mistral's `response_format` field; see the [chat completion API documentation](https://docs.mistral.ai/fr/api/endpoint/chat#operation-chat_completion_v1_chat_completions_post) for supported formats and JSON Schema options.

Configure function tools and a tool choice for chat completions. Tool settings are reused until replaced or cleared:

```php
$client->chat
    ->tools([[
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'description' => 'Get the current weather for a location.',
            'parameters' => [
                'type' => 'object',
                'properties' => ['location' => ['type' => 'string']],
                'required' => ['location'],
            ],
        ],
    ]])
    ->toolChoice('auto');

$response = $client->chat->complete(
    model: 'mistral-large-latest',
    messages: [new UserMessage('What is the weather in Paris?')]
);

// Select a particular function or clear the configuration.
$client->chat->toolChoice(['type' => 'function', 'function' => ['name' => 'get_weather']]);
$client->chat->tools(null)->toolChoice(null);
```

Tool definitions are sent as Mistral's `tools` field, and the configured string or structured choice is sent as `tool_choice`.

`SystemMessage`, `AssistantMessage`, and `ToolMessage` can also be used to construct conversation messages. Assistant tool calls and tool results retain their metadata when serialized:

```php
$messages = [
    new SystemMessage('You are a helpful assistant.'),
    new UserMessage('What is 1 + 1?'),
    new AssistantMessage(null, [[
        'id' => 'call_123',
        'type' => 'function',
        'function' => ['name' => 'calculator', 'arguments' => '{"expression":"1 + 1"}'],
    ]]),
    new ToolMessage('2', 'call_123', 'calculator'),
    new AssistantMessage('The result is 2.'),
    new AssistantMessage('Continue from this prefix', prefix: true),
];
```

## License

This project is licensed under the GNU General Public License v3.0 or later. See [LICENSE](LICENSE) for the full text.