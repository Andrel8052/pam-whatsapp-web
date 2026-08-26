<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class PollOption
{
    public function __construct(public string $name, public int $localId)
    {
    }
}
