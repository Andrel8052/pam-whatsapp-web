<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class CreateChannelResult
{
    public string $title;
    public ContactId $nid;
    public string $inviteLink;
    public int $createdAtTs;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->title = Payload::string($payload, 'title');
        $this->nid = ContactId::fromPayload(Payload::object($payload['nid'] ?? null, 'Created channel id'));
        $this->inviteLink = Payload::string($payload, 'inviteLink');
        $this->createdAtTs = Payload::int($payload, 'createdAtTs');
    }
}
