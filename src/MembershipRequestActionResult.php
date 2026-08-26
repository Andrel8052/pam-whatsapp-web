<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class MembershipRequestActionResult
{
    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $error = $payload['error'] ?? null;

        return new self(
            self::requesterId($payload['requesterId'] ?? null),
            is_int($error) ? $error : null,
            Payload::string($payload, 'message'),
        );
    }

    /** @param string|list<string>|null $requesterId */
    public function __construct(public string|array|null $requesterId, public ?int $error, public string $message)
    {
    }

    /** @return string|list<string>|null */
    private static function requesterId(mixed $value): string|array|null
    {
        if (is_string($value)) return $value;
        if (!is_array($value)) return null;
        $ids = [];
        foreach ($value as $id) {
            if (is_string($id)) $ids[] = $id;
        }

        return $ids;
    }
}
