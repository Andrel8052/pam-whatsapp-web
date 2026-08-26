<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\EventType;
use Pam\WhatsApp\Message;

final readonly class MessageLifecycle
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public EventType $type,
        public Message $message,
        public array $context = [],
    ) {
    }
}
