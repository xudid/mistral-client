<?php

namespace Xudid\MistralClient\Message;

final class SystemMessage extends Message
{
    public function __construct(string|array $content)
    {
        parent::__construct('system', $content);
    }
}