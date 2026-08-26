<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;

final readonly class Product
{
    public function __construct(
        private Session $session,
        public string $id,
        public string $price,
        public string $thumbnailUrl,
        public string $currency,
        public string $name,
        public int $quantity,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(Session $session, array $payload): self
    {
        return new self(
            $session,
            Payload::string($payload, 'id'),
            Payload::string($payload, 'price'),
            Payload::string($payload, 'thumbnailUrl'),
            Payload::string($payload, 'currency'),
            Payload::string($payload, 'name'),
            Payload::int($payload, 'quantity'),
        );
    }

    public function getData(): ?ProductMetadata
    {
        $value = $this->session->invoke('getProductMetadata', [$this->id]);

        return $value === null ? null : new ProductMetadata(Payload::object($value, 'Product metadata'));
    }
}
