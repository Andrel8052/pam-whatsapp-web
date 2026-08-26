<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class ContactId
{
    public string $_serialized;

    public function __construct(
        public string $server,
        public string $user,
        public string $serialized,
    ) {
        $this->_serialized = $serialized;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $serialized = Payload::string($payload, '_serialized');
        $derived = self::fromSerialized($serialized);

        return new self(
            Payload::string($payload, 'server', $derived->server),
            Payload::string($payload, 'user', $derived->user),
            $serialized,
        );
    }

    public static function fromSerialized(string $serialized): self
    {
        $separator = strrpos($serialized, '@');
        if ($separator === false || $separator === 0 || $separator === strlen($serialized) - 1) {
            throw new \UnexpectedValueException('A contact id must use the user@server format.');
        }

        return new self(
            substr($serialized, $separator + 1),
            substr($serialized, 0, $separator),
            $serialized,
        );
    }

    public static function fromValue(mixed $value): self
    {
        if (is_string($value)) return self::fromSerialized($value);
        if (is_array($value)) return self::fromPayload(Payload::object($value, 'Contact id'));

        throw new \UnexpectedValueException('A contact id must be a serialized string or an object payload.');
    }
}
