<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

final readonly class LocalAuthOptions
{
    public function __construct(
        public ?string $clientId = null,
        public ?string $dataPath = null,
        public int $rmMaxRetries = 4,
    ) {}
}
