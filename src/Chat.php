<?php

namespace Xudid\MistralClient;

use GuzzleHttp\ClientInterface;
use JsonException;

final class Chat
{
    private const COMPLETIONS_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

    private ?ResponseFormat $responseFormat = null;
    private ?array $tools = null;
    private string|array|null $toolChoice = null;

    public function __construct(
        private string $apiKey,
        private ClientInterface $httpClient
    ) {
    }

    public function responseFormat(?ResponseFormat $responseFormat): self
    {
        $this->responseFormat = $responseFormat;

        return $this;
    }

    public function tools(?array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    public function toolChoice(string|array|null $toolChoice): self
    {
        $this->toolChoice = $toolChoice;

        return $this;
    }

    /**
     * @throws JsonException
     */
    public function complete(string $model, array $messages): array
    {
        $requestBody = [
            'model' => $model,
            'messages' => $messages,
        ];
        if ($this->responseFormat !== null) {
            $requestBody['response_format'] = $this->responseFormat->jsonSerialize();
        }
        if ($this->tools !== null) {
            $requestBody['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $requestBody['tool_choice'] = $this->toolChoice;
        }

        $response = $this->httpClient->request('POST', self::COMPLETIONS_ENDPOINT, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'json' => $requestBody,
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
