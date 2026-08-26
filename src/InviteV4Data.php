<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class InviteV4Data
{
    public function __construct(
        public string $inviteCode,
        public int $inviteCodeExp,
        public string $groupId,
        public ?string $groupName,
        public string $fromId,
        public string $toId,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $groupName = $payload['groupName'] ?? null;

        return new self(
            Payload::string($payload, 'inviteCode'),
            Payload::int($payload, 'inviteCodeExp'),
            Payload::string($payload, 'groupId'),
            is_string($groupName) ? $groupName : null,
            Payload::string($payload, 'fromId'),
            Payload::string($payload, 'toId'),
        );
    }

    /** @return array<string, mixed> */
    public function toBridge(): array
    {
        return get_object_vars($this);
    }
}
