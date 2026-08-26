<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class MessageId implements \Stringable
{
    public string $_serialized;

    public function __construct(
        public bool $fromMe,
        public string $remote,
        public string $id,
        public string $serialized,
    ) {
        $this->_serialized = $serialized;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            Payload::bool($payload, 'fromMe'),
            Payload::string($payload, 'remote'),
            Payload::string($payload, 'id'),
            Payload::string($payload, '_serialized'),
        );
    }

    public static function fromSerialized(string $serialized, bool $fromMe, string $remote): self
    {
        $separator = strrpos($serialized, '_');
        $id = $separator === false ? $serialized : substr($serialized, $separator + 1);

        return new self($fromMe, $remote, $id, $serialized);
    }

    public function __toString(): string
    {
        return $this->serialized;
    }
}
