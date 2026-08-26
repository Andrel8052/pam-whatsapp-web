<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class ProductMetadata
{
    public string $id;
    public string $name;
    public string $description;
    public string $retailer_id;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = Payload::string($payload, 'id');
        $this->name = Payload::string($payload, 'name');
        $this->description = Payload::string($payload, 'description');
        $this->retailer_id = Payload::string($payload, 'retailer_id');
    }
}
