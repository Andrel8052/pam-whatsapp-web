<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\Message;

final readonly class ContactChanged
{
    public function __construct(
        public Message $message,
        public string $oldId,
        public string $newId,
        public bool $isContact,
    ) {
    }
}
