<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Support;

use Pam\WhatsApp\Chat;
use Pam\WhatsApp\Channel;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\GroupChat;
use Pam\WhatsApp\PrivateChat;

final class ChatFactory
{
    /** @param array<string, mixed> $payload */
    public static function make(Session $session, array $payload): Chat|Channel
    {
        if (Payload::bool($payload, 'isChannel')) {
            return new Channel($session, $payload);
        }

        return Payload::bool($payload, 'isGroup')
            ? new GroupChat($session, $payload)
            : new PrivateChat($session, $payload);
    }
}
