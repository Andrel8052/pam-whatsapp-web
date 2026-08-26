<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class ChatRemoved
{
    public function __construct(public string $chatId)
    {
    }
}
