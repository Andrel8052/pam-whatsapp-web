<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class ChatArchiveChanged
{
    public function __construct(
        public string $chatId,
        public bool $archived,
        public bool $previousArchived,
    ) {
    }
}
