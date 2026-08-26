<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\DisconnectionReason;

final readonly class Disconnected
{
    public function __construct(public DisconnectionReason $reason)
    {
    }
}
