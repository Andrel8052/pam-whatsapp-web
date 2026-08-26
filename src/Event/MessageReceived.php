<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\Message;

final readonly class MessageReceived
{
    public function __construct(public Message $message)
    {
    }
}
