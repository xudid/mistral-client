<?php

namespace Xudid\MistralClient\Message;

final class ToolMessage extends Message
{
    public function __construct(
        string|array $content,
        private string $toolCallId,
        private ?string $name = null
    ) {
        parent::__construct('tool', $content);
    }

    public function jsonSerialize(): array
    {
        $message = parent::jsonSerialize();
        $message['tool_call_id'] = $this->toolCallId;

        if ($this->name !== null) {
            $message['name'] = $this->name;
        }

        return $message;
    }
}