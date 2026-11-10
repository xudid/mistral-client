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

$client = new Mistral(apiKey: getenv('MISTRAL_API_KEY'));
$response = $client->chat->complete(
    model: 'mistral-large-latest',
    messages: [new UserMessage('Hello')]
);

echo $response['choices'][0]['message']['content'];
```

`complete()` sends a synchronous request to the chat-completions endpoint and returns the full decoded JSON response as an associative array. HTTP and network errors are raised by Guzzle, and invalid JSON responses raise `JsonException`.

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