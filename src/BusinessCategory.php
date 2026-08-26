<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class BusinessCategory
{
    public string $id;
    public string $localized_display_name;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = Payload::string($payload, 'id');
        $this->localized_display_name = Payload::string($payload, 'localized_display_name');
    }
}
