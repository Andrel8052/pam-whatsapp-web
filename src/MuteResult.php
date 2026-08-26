<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class MuteResult
{
    public function __construct(public bool $isMuted, public int $muteExpiration)
    {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            Payload::bool($payload, 'isMuted'),
            Payload::int($payload, 'muteExpiration'),
        );
    }
}
