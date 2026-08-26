<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

final readonly class RemoteStoreOptions
{
    public function __construct(
        public string $session,
        public ?string $path = null,
    ) {
        if ($session === '' || str_contains($session, "\0") || ($path !== null && str_contains($path, "\0"))) {
            throw new \InvalidArgumentException('Remote store options are invalid.');
        }
    }
}
