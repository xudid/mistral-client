<?php

namespace Xudid\MistralClient;

use JsonSerializable;

final class ResponseFormat implements JsonSerializable
{
    public function __construct(
        private string $type,
        private ?array $jsonSchema = null
    ) {
    }

    public function jsonSerialize(): array
    {
        $responseFormat = ['type' => $this->type];

        if ($this->jsonSchema !== null) {
            $responseFormat['json_schema'] = $this->jsonSchema;
        }

        return $responseFormat;
    }
}
