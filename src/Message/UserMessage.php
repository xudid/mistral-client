<?php

namespace Xudid\MistralClient\Message;

final class UserMessage extends Message
{
    public function __construct(string|array $content)
    {
        parent::__construct('user', $content);
    }
}