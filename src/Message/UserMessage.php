<?php

namespace Xudid\MistralClient\Message;

use JsonSerializable;

final class UserMessage implements JsonSerializable
{
    public function __construct(private string|array $content)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'role' => 'user',
            'content' => $this->content,
        ];
    }
}