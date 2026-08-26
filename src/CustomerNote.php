<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Exception\BridgeException;
use Pam\WhatsApp\Support\Payload;

final readonly class CustomerNote
{
    public function __construct(
        public string $chatId,
        public string $content,
        public int $createdAt,
        public string $id,
        public int $modifiedAt,
        public CustomerNoteType $type,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $type = $payload['type'] ?? null;
        if (!is_int($type)) {
            throw new BridgeException('Customer note type must be an integer.');
        }

        return new self(
            Payload::string($payload, 'chatId'),
            Payload::string($payload, 'content'),
            Payload::int($payload, 'createdAt'),
            Payload::string($payload, 'id'),
            Payload::int($payload, 'modifiedAt'),
            CustomerNoteType::from($type),
        );
    }
}
