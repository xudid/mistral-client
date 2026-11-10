<?php

namespace Xudid\MistralClient\Message;

use JsonSerializable;

final class SystemMessage implements JsonSerializable
{
    public function __construct(private string|array $content)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'role' => 'system',
            'content' => $this->content,
        ];
    }
}