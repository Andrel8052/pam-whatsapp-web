<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class GroupParticipant
{
    public ContactId $id;
    public bool $isAdmin;
    public bool $isSuperAdmin;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = ContactId::fromPayload(Payload::object($payload['id'] ?? null, 'Group participant id'));
        $this->isAdmin = Payload::bool($payload, 'isAdmin');
        $this->isSuperAdmin = Payload::bool($payload, 'isSuperAdmin');
    }
}
