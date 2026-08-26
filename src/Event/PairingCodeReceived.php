<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class PairingCodeReceived
{
    public function __construct(public string $code)
    {
        if ($code === '') {
            throw new \InvalidArgumentException('Pairing code cannot be empty.');
        }
    }
}
