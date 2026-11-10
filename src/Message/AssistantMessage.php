<?php

namespace Xudid\MistralClient\Message;

use JsonSerializable;

final class AssistantMessage implements JsonSerializable
{
    public function __construct(
        private string|array|null $content = null,
        private ?array $toolCalls = null,
        private ?bool $prefix = null
    ) {
    }

    public function jsonSerialize(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => $this->content,
        ];

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = $this->toolCalls;
        }

        if ($this->prefix !== null) {
            $message['prefix'] = $this->prefix;
        }

        return $message;
    }
}