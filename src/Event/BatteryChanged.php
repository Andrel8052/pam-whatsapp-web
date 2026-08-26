<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class BatteryChanged
{
    public function __construct(public int $battery, public bool $plugged)
    {
        if ($battery < 0 || $battery > 100) {
            throw new \InvalidArgumentException('Battery percentage must be between 0 and 100.');
        }
    }
}
