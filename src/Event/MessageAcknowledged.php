<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\Message;
use Pam\WhatsApp\MessageAck;

final readonly class MessageAcknowledged
{
    public function __construct(public Message $message, public MessageAck $ack)
    {
    }
}
