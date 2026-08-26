<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

final class NoAuth extends AuthStrategy
{
    public function prepare(): ?string
    {
        return null;
    }
}
