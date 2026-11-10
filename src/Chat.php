<?php

namespace Xudid\MistralClient;

use GuzzleHttp\ClientInterface;
use JsonException;

final class Chat
{
    private const COMPLETIONS_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

    public function __construct(
        private string $apiKey,
        private ClientInterface $httpClient
    ) {
    }

    /**
     * @throws JsonException
     */
    public function complete(string $model, array $messages): array
    {
        $response = $this->httpClient->request('POST', self::COMPLETIONS_ENDPOINT, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'json' => [
                'model' => $model,
                'messages' => $messages,
            ],
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
