<?php

namespace Xudid\MistralClient\Message;

use JsonSerializable;

final class ToolMessage implements JsonSerializable
{
    public function __construct(
        private string|array $content,
        private string $toolCallId,
        private ?string $name = null
    ) {
    }

    public function jsonSerialize(): array
    {
        $message = [
            'role' => 'tool',
            'content' => $this->content,
            'tool_call_id' => $this->toolCallId,
        ];

        if ($this->name !== null) {
            $message['name'] = $this->name;
        }

        return $message;
    }
}