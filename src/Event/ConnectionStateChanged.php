<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\ConnectionState;

final readonly class ConnectionStateChanged
{
    public function __construct(public ConnectionState $state)
    {
    }
}
