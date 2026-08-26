<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Contract;

use Pam\WhatsApp\MessageContentType;
use Pam\WhatsApp\MessageType;

final readonly class MessageData
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $chatId,
        public string $from,
        public string $to,
        public string $body,
        public bool $fromMe,
        public int $timestamp,
        public MessageType $type,
        public MessageContentType $contentType,
        public array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            self::string($payload, 'id'),
            self::string($payload, 'chatId'),
            self::string($payload, 'from'),
            self::string($payload, 'to'),
            self::string($payload, 'body'),
            self::bool($payload, 'fromMe'),
            self::int($payload, 'timestamp'),
            MessageType::from(self::int($payload, 'type')),
            MessageContentType::from(self::int($payload, 'contentType')),
            $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException("Message field {$key} must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function bool(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;
        if (!is_bool($value)) {
            throw new \UnexpectedValueException("Message field {$key} must be a boolean.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function int(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_int($value)) {
            throw new \UnexpectedValueException("Message field {$key} must be an integer.");
        }

        return $value;
    }
}
