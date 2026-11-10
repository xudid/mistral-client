<?php

namespace Xudid\MistralClient\Message;

final class AssistantMessage extends Message
{
    public function __construct(
        string|array|null $content = null,
        private ?array $toolCalls = null,
        private ?bool $prefix = null
    ) {
        parent::__construct('assistant', $content);
    }

    public function jsonSerialize(): array
    {
        $message = parent::jsonSerialize();

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = $this->toolCalls;
        }

        if ($this->prefix !== null) {
            $message['prefix'] = $this->prefix;
        }

        return $message;
    }
}