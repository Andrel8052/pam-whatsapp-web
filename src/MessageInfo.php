<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class MessageInfo
{
    /** @var list<MessageReceipt> */
    public array $delivery;
    /** @var list<MessageReceipt> */
    public array $played;
    /** @var list<MessageReceipt> */
    public array $read;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->delivery = $this->receipts($payload['delivery'] ?? [], 'Delivery receipts');
        $this->deliveryRemaining = Payload::int($payload, 'deliveryRemaining');
        $this->played = $this->receipts($payload['played'] ?? [], 'Played receipts');
        $this->playedRemaining = Payload::int($payload, 'playedRemaining');
        $this->read = $this->receipts($payload['read'] ?? [], 'Read receipts');
        $this->readRemaining = Payload::int($payload, 'readRemaining');
    }

    public int $deliveryRemaining;
    public int $playedRemaining;
    public int $readRemaining;

    /** @return list<MessageReceipt> */
    private function receipts(mixed $value, string $label): array
    {
        return array_map(
            static fn (array $receipt): MessageReceipt => new MessageReceipt(
                ContactId::fromPayload(Payload::object($receipt['id'] ?? null, $label.' id')),
                Payload::int($receipt, 'timestamp'),
            ),
            Payload::objects($value, $label),
        );
    }
}
