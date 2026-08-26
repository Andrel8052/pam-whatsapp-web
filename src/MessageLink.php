<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageLink
{
    public function __construct(public string $link, public bool $isSuspicious)
    {
    }
}
