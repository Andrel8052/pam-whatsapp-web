<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

use Pam\WhatsApp\EventType;

final class AuthenticationHandshake
{
    private bool $checked = false;

    public function __construct(private readonly ?AuthStrategy $strategy)
    {
    }

    public function inspect(EventType $event): ?AuthenticationDecision
    {
        if ($this->checked
            || $this->strategy === null
            || !in_array($event, [EventType::QrCode, EventType::PairingCodeReceived], true)
        ) {
            return null;
        }
        $this->checked = true;

        return AuthenticationDecision::fromResult($this->strategy->onAuthenticationNeeded());
    }
}
