<?php

namespace Xudid\MistralClient\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonException;
use PHPUnit\Framework\TestCase;
use Xudid\MistralClient\Mistral;
use Xudid\MistralClient\Message\AssistantMessage;
use Xudid\MistralClient\Message\SystemMessage;
use Xudid\MistralClient\Message\ToolMessage;
use Xudid\MistralClient\Message\UserMessage;

final class MistralTest extends TestCase
{
    public function testCompleteSendsAuthenticatedChatCompletionRequest(): void
    {
        $messages = [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hello']]]];
        $expectedResponse = [
            'id' => 'cmpl-test',
            'choices' => [['message' => ['content' => 'Hello']]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ];
        $history = [];
        $httpClient = $this->createMockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expectedResponse)),
        ], $history);
        $client = new Mistral('test-api-key', $httpClient);

        $response = $client->chat->complete('mistral-large-latest', $messages);

        self::assertSame($expectedResponse, $response);
        self::assertCount(1, $history);
        self::assertSame('POST', $history[0]['request']->getMethod());
        self::assertSame(
            'https://api.mistral.ai/v1/chat/completions',
            (string) $history[0]['request']->getUri()
        );
        self::assertSame('Bearer test-api-key', $history[0]['request']->getHeaderLine('Authorization'));
        self::assertSame('application/json', $history[0]['request']->getHeaderLine('Content-Type'));
        self::assertSame(
            ['model' => 'mistral-large-latest', 'messages' => $messages],
            json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCompleteCanBeCalledMoreThanOnce(): void
    {
        $history = [];
        $httpClient = $this->createMockClient([
            new Response(200, [], '{"id":"first"}'),
            new Response(200, [], '{"id":"second"}'),
        ], $history);
        $client = new Mistral('test-api-key', $httpClient);

        $firstResponse = $client->chat->complete('mistral-large-latest', [['role' => 'user', 'content' => 'First']]);
        $secondResponse = $client->chat->complete('mistral-large-latest', [['role' => 'user', 'content' => 'Second']]);

        self::assertSame(['id' => 'first'], $firstResponse);
        self::assertSame(['id' => 'second'], $secondResponse);
        self::assertCount(2, $history);
    }

    public function testCompleteSerializesUserMessagesAndPreservesArrayMessages(): void
    {
        $messages = [
            new UserMessage('Hello'),
            new UserMessage([['type' => 'text', 'text' => 'What is in this image?']]),
            ['role' => 'assistant', 'content' => 'I can help with that.'],
        ];
        $history = [];
        $httpClient = $this->createMockClient([
            new Response(200, [], '{"id":"cmpl-test"}'),
        ], $history);
        $client = new Mistral('test-api-key', $httpClient);

        $client->chat->complete('mistral-large-latest', $messages);

        self::assertSame(
            [
                'model' => 'mistral-large-latest',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                    ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'What is in this image?']]],
                    ['role' => 'assistant', 'content' => 'I can help with that.'],
                ],
            ],
            json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCompleteSerializesSystemAssistantAndToolMessages(): void
    {
        $messages = [
            new SystemMessage('You are a helpful assistant.'),
            new AssistantMessage([['type' => 'text', 'text' => 'I will calculate that.']]),
            new AssistantMessage(null, [[
                'id' => 'call_123',
                'type' => 'function',
                'function' => ['name' => 'calculator', 'arguments' => '{"expression":"1 + 1"}'],
            ]]),
            new AssistantMessage('Continue from this prefix', prefix: true),
            new AssistantMessage(null, [[
                'id' => 'call_456',
                'type' => 'function',
                'function' => ['name' => 'calculator', 'arguments' => '{"expression":"2 + 2"}'],
            ]], false),
            new ToolMessage('2', 'call_123', 'calculator'),
            new ToolMessage([['type' => 'text', 'text' => 'Done.']], 'call_456'),
            new AssistantMessage('The result is 2.'),
        ];
        $history = [];
        $httpClient = $this->createMockClient([
            new Response(200, [], '{"id":"cmpl-test"}'),
        ], $history);
        $client = new Mistral('test-api-key', $httpClient);

        $client->chat->complete('mistral-large-latest', $messages);

        self::assertSame(
            [
                'model' => 'mistral-large-latest',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'I will calculate that.']]],
                    [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_123',
                            'type' => 'function',
                            'function' => ['name' => 'calculator', 'arguments' => '{"expression":"1 + 1"}'],
                        ]],
                    ],
                    [
                        'role' => 'assistant',
                        'content' => 'Continue from this prefix',
                        'prefix' => true,
                    ],
                    [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_456',
                            'type' => 'function',
                            'function' => ['name' => 'calculator', 'arguments' => '{"expression":"2 + 2"}'],
                        ]],
                        'prefix' => false,
                    ],
                    [
                        'role' => 'tool',
                        'content' => '2',
                        'tool_call_id' => 'call_123',
                        'name' => 'calculator',
                    ],
                    [
                        'role' => 'tool',
                        'content' => [['type' => 'text', 'text' => 'Done.']],
                        'tool_call_id' => 'call_456',
                    ],
                    ['role' => 'assistant', 'content' => 'The result is 2.'],
                ],
            ],
            json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCompletePropagatesHttpErrors(): void
    {
        $client = new Mistral('test-api-key', $this->createMockClient([
            new Response(401, [], '{"message":"Unauthorized"}'),
        ]));

        $this->expectException(ClientException::class);

        $client->chat->complete('mistral-large-latest', [['role' => 'user', 'content' => 'Hello']]);
    }

    public function testCompletePropagatesNetworkErrors(): void
    {
        $request = new Request('POST', 'https://api.mistral.ai/v1/chat/completions');
        $client = new Mistral('test-api-key', $this->createMockClient([
            new RequestException('Connection failed', $request),
        ]));

        $this->expectException(RequestException::class);

        $client->chat->complete('mistral-large-latest', [['role' => 'user', 'content' => 'Hello']]);
    }

    public function testCompleteRejectsInvalidJsonResponses(): void
    {
        $client = new Mistral('test-api-key', $this->createMockClient([
            new Response(200, [], '{invalid json'),
        ]));

        $this->expectException(JsonException::class);

        $client->chat->complete('mistral-large-latest', [['role' => 'user', 'content' => 'Hello']]);
    }

    private function createMockClient(array $responses, array &$history = []): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
    }
}