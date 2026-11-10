<?php

namespace Xudid\MistralClient\Message;

use JsonSerializable;

abstract class Message implements JsonSerializable
{
    protected function __construct(
        private string $role,
        private string|array|null $content
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}