<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class ProxyAuthentication
{
    public function __construct(public string $username, public string $password)
    {
        if ($username === '' || str_contains($username.$password, "\0")) {
            throw new \InvalidArgumentException('Proxy credentials are invalid.');
        }
    }
}
