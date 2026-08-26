<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class EventLocation
{
    public function __construct(
        public float $degreesLatitude,
        public float $degreesLongitude,
        public string $name,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            self::number($payload['degreesLatitude'] ?? null, 'degreesLatitude'),
            self::number($payload['degreesLongitude'] ?? null, 'degreesLongitude'),
            Payload::string($payload, 'name'),
        );
    }

    private static function number(mixed $value, string $field): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \UnexpectedValueException("Event location field {$field} must be numeric.");
        }

        return (float) $value;
    }
}
