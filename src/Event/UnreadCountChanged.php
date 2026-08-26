<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class UnreadCountChanged
{
    public function __construct(public string $chatId, public int $unreadCount)
    {
    }
}
