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
use Xudid\MistralClient\Message\Message;
use Xudid\MistralClient\Message\SystemMessage;
use Xudid\MistralClient\Message\ToolMessage;
use Xudid\MistralClient\Message\UserMessage;
use Xudid\MistralClient\ResponseFormat;

final class MistralTest extends TestCase
{
    public function testMessagesExtendCommonBaseAndPreserveNamedArguments(): void
    {
        $messages = [
            new UserMessage(content: 'Hello'),
            new SystemMessage(content: 'You are helpful.'),
            new AssistantMessage(content: 'Working', toolCalls: null, prefix: false),
            new ToolMessage(content: 'Done', toolCallId: 'call_123', name: 'calculator'),
        ];

        foreach ($messages as $message) {
            self::assertInstanceOf(Message::class, $message);
        }

        self::assertSame(['role' => 'user', 'content' => 'Hello'], $messages[0]->jsonSerialize());
        self::assertSame(['role' => 'system', 'content' => 'You are helpful.'], $messages[1]->jsonSerialize());
        self::assertSame(
            ['role' => 'assistant', 'content' => 'Working', 'prefix' => false],
            $messages[2]->jsonSerialize()
        );
        self::assertSame(
            ['role' => 'tool', 'content' => 'Done', 'tool_call_id' => 'call_123', 'name' => 'calculator'],
            $messages[3]->jsonSerialize()
        );
    }

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

    public function testCompleteSendsConfiguredResponseFormats(): void
    {
        $messages = [['role' => 'user', 'content' => 'Return a result.']];
        $responseFormats = [
            new ResponseFormat('json_object'),
            new ResponseFormat('text'),
            new ResponseFormat('json_schema', [
                'name' => 'result',
                'schema' => [
                    'type' => 'object',
                    'properties' => ['value' => ['type' => 'string']],
                    'required' => ['value'],
                ],
            ]),
        ];
        $history = [];
        $httpClient = $this->createMockClient([
            new Response(200, [], '{"id":"json-object"}'),
            new Response(200, [], '{"id":"text"}'),
            new Response(200, [], '{"id":"json-schema"}'),
        ], $history);
        $client = new Mistral('test-api-key', $httpClient);

        foreach ($responseFormats as $responseFormat) {
            $client->chat->responseFormat($responseFormat);
            $client->chat->complete('mistral-large-latest', $messages);
        }

        self::assertCount(3, $history);
        self::assertCount(2, (new \ReflectionMethod($client->chat, 'complete'))->getParameters());
        foreach ($responseFormats as $index => $responseFormat) {
            self::assertSame(
                [
                    'model' => 'mistral-large-latest',
                    'messages' => $messages,
                    'response_format' => $responseFormat->jsonSerialize(),
                ],
                json_decode((string) $history[$index]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        }
    }

    public function testResponseFormatCanBeConfiguredFluentlyReplacedAndCleared(): void
    {
        $messages = [['role' => 'user', 'content' => 'Return a result.']];
        $configuredFormat = new ResponseFormat('json_object');
        $overrideFormat = new ResponseFormat('json_schema', [
            'name' => 'result',
            'schema' => [
                'type' => 'object',
                'properties' => ['value' => ['type' => 'string']],
                'required' => ['value'],
            ],
        ]);
        $history = [];
        $httpClient = $this->createMockClient([
            new Response(200, [], '{"id":"configured-first"}'),
            new Response(200, [], '{"id":"configured-second"}'),
            new Response(200, [], '{"id":"override"}'),
            new Response(200, [], '{"id":"cleared"}'),
        ], $history);
        $client = new Mistral('test-api-key', $httpClient);

        self::assertSame($client->chat, $client->chat->responseFormat($configuredFormat));
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->responseFormat($overrideFormat);
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->responseFormat(null);
        $client->chat->complete('mistral-large-latest', $messages);

        self::assertSame(
            ['type' => 'json_object'],
            json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)['response_format']
        );
        self::assertSame(
            ['type' => 'json_object'],
            json_decode((string) $history[1]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)['response_format']
        );
        self::assertSame(
            $overrideFormat->jsonSerialize(),
            json_decode((string) $history[2]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)['response_format']
        );
        self::assertSame(
            ['model' => 'mistral-large-latest', 'messages' => $messages],
            json_decode((string) $history[3]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testToolsAndToolChoiceCanBeConfiguredFluentlyReplacedAndCleared(): void
    {
        $messages = [['role' => 'user', 'content' => 'What is the weather?']];
        $tools = [[
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'description' => 'Get the current weather.',
                'parameters' => (object) [],
            ],
        ]];
        $replacementTools = [[
            'type' => 'function',
            'function' => [
                'name' => 'get_forecast',
                'description' => 'Get a weather forecast.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['location' => ['type' => 'string']],
                    'required' => ['location'],
                ],
            ],
        ]];
        $toolChoice = ['type' => 'function', 'function' => ['name' => 'get_forecast']];
        $history = [];
        $httpClient = $this->createMockClient([
            new Response(200, [], '{"id":"first"}'),
            new Response(200, [], '{"id":"persistent"}'),
            new Response(200, [], '{"id":"replaced"}'),
            new Response(200, [], '{"id":"choice-cleared"}'),
            new Response(200, [], '{"id":"empty-tools"}'),
            new Response(200, [], '{"id":"tools-cleared"}'),
        ], $history);
        $client = new Mistral('test-api-key', $httpClient);
        $client->chat->responseFormat(new ResponseFormat('json_object'));

        self::assertSame($client->chat, $client->chat->tools($tools));
        self::assertSame($client->chat, $client->chat->toolChoice('auto'));
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->tools($replacementTools)->toolChoice($toolChoice);
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->toolChoice(null);
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->tools([]);
        $client->chat->complete('mistral-large-latest', $messages);
        $client->chat->tools(null);
        $client->chat->complete('mistral-large-latest', $messages);

        self::assertCount(6, $history);
        $firstRequest = json_decode((string) $history[0]['request']->getBody(), false, 512, JSON_THROW_ON_ERROR);
        self::assertSame('json_object', $firstRequest->response_format->type);
        self::assertSame('auto', $firstRequest->tool_choice);
        self::assertSame('get_weather', $firstRequest->tools[0]->function->name);
        self::assertInstanceOf(\stdClass::class, $firstRequest->tools[0]->function->parameters);

        $persistentRequest = json_decode((string) $history[1]['request']->getBody(), false, 512, JSON_THROW_ON_ERROR);
        self::assertSame('auto', $persistentRequest->tool_choice);
        self::assertSame('get_weather', $persistentRequest->tools[0]->function->name);

        $replacedRequest = json_decode((string) $history[2]['request']->getBody(), false, 512, JSON_THROW_ON_ERROR);
        self::assertSame('get_forecast', $replacedRequest->tools[0]->function->name);
        self::assertSame($toolChoice, json_decode(json_encode($replacedRequest->tool_choice, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));

        $choiceClearedRequest = json_decode((string) $history[3]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('tool_choice', $choiceClearedRequest);
        self::assertSame('get_forecast', $choiceClearedRequest['tools'][0]['function']['name']);

        $emptyToolsRequest = json_decode((string) $history[4]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $emptyToolsRequest['tools']);
        self::assertArrayNotHasKey('tool_choice', $emptyToolsRequest);

        $clearedRequest = json_decode((string) $history[5]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('tools', $clearedRequest);
        self::assertArrayNotHasKey('tool_choice', $clearedRequest);
        self::assertSame('json_object', $clearedRequest['response_format']['type']);
    }

    public function testResponseFormatSerializesNestedSchemaAndEmptyObjects(): void
    {
        $responseFormat = new ResponseFormat('json_schema', [
            'name' => 'result',
            'schema' => (object) [],
        ]);

        self::assertSame(
            '{"type":"json_schema","json_schema":{"name":"result","schema":{}}}',
            json_encode($responseFormat, JSON_THROW_ON_ERROR)
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