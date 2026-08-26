<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class AddParticipantResult
{
    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            Payload::int($payload, 'code'),
            Payload::string($payload, 'message'),
            Payload::bool($payload, 'isInviteV4Sent'),
        );
    }

    public function __construct(
        public int $code,
        public string $message,
        public bool $isInviteV4Sent,
    ) {
    }
}
