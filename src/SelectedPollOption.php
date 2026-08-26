<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class SelectedPollOption
{
    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(Payload::int($payload, 'id'), Payload::string($payload, 'name'));
    }

    public function __construct(public int $id, public string $name)
    {
    }
}
