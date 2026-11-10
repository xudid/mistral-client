<?php

namespace Xudid\MistralClient;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class Mistral
{
    public Chat $chat;

    public function __construct(string $apiKey, ?ClientInterface $httpClient = null)
    {
        $this->chat = new Chat($apiKey, $httpClient ?? new Client());
    }
}
