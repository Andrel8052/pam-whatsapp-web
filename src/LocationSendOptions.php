<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class LocationSendOptions
{
    public function __construct(
        public ?string $name = null,
        public ?string $address = null,
        public ?string $url = null,
    ) {
    }
}
