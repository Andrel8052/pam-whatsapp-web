<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class LoadingScreen
{
    public function __construct(public int $percent, public string $message)
    {
        if ($percent < 0 || $percent > 100) {
            throw new \InvalidArgumentException('Loading percentage must be between 0 and 100.');
        }
    }
}
