<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class ReactionList
{
    /** @var list<Reaction> */
    public array $senders;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = Payload::string($payload, 'id');
        $this->aggregateEmoji = Payload::string($payload, 'aggregateEmoji');
        $this->hasReactionByMe = Payload::bool($payload, 'hasReactionByMe');
        $this->senders = array_map(
            static fn (array $sender): Reaction => new Reaction($sender),
            Payload::objects($payload['senders'] ?? [], 'Reaction senders'),
        );
    }

    public string $id;
    public string $aggregateEmoji;
    public bool $hasReactionByMe;
}
