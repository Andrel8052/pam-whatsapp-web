<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class CreateGroupResult
{
    public string $title;
    public ContactId $gid;

    /** @var array<string, CreateGroupParticipantResult> */
    public array $participants;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->title = Payload::string($payload, 'title');
        $this->gid = ContactId::fromPayload(Payload::object($payload['gid'] ?? null, 'Created group id'));
        $participants = Payload::object($payload['participants'] ?? [], 'Created group participants');
        $results = [];
        foreach ($participants as $participantId => $result) {
            $results[$participantId] = new CreateGroupParticipantResult(
                Payload::object($result, 'Created group participant result'),
            );
        }
        $this->participants = $results;
    }
}
