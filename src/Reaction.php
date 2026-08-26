<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class Reaction
{
    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = MessageId::fromPayload(Payload::object($payload['id'] ?? null, 'Reaction id'));
        $this->orphan = Payload::int($payload, 'orphan');
        $reason = $payload['orphanReason'] ?? null;
        $this->orphanReason = is_string($reason) ? $reason : null;
        $this->timestamp = Payload::int($payload, 'timestamp');
        $this->reaction = Payload::string($payload, 'reaction');
        $this->read = Payload::bool($payload, 'read');
        $this->msgId = MessageId::fromPayload(Payload::object($payload['msgId'] ?? null, 'Reacted message id'));
        $this->senderId = Payload::string($payload, 'senderId');
        $ack = $payload['ack'] ?? null;
        $this->ack = is_int($ack) ? MessageAck::tryFrom($ack) : null;
    }

    public MessageId $id;
    public int $orphan;
    public ?string $orphanReason;
    public int $timestamp;
    public string $reaction;
    public bool $read;
    public MessageId $msgId;
    public string $senderId;
    public ?MessageAck $ack;
}
