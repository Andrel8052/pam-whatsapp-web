<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MediaFromURLOptions
{
    /**
     * @param array<string, mixed>|null $reqOptions
     */
    public function __construct(
        public bool $unsafeMime = false,
        public ?string $filename = null,
        public ?Client $client = null,
        public ?array $reqOptions = null,
    ) {
    }
}
