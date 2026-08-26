<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Bridge;

use Pam\WhatsApp\EventType;
use Pam\WhatsApp\Exception\BridgeException;

final readonly class BridgeEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(public EventType $type, public array $payload)
    {
    }

    public static function fromValue(mixed $value): self
    {
        if (!is_array($value) || !is_int($value['type'] ?? null)) {
            throw new BridgeException('WhatsApp bridge event must contain an integer type.');
        }
        $rawPayload = $value['payload'] ?? [];
        if (!is_array($rawPayload)) {
            throw new BridgeException('WhatsApp bridge event payload must be an object.');
        }

        $payload = [];
        foreach ($rawPayload as $key => $item) {
            if (!is_string($key)) {
                throw new BridgeException('WhatsApp bridge event payload must use string keys.');
            }
            $payload[$key] = $item;
        }

        return new self(EventType::from($value['type']), $payload);
    }
}
