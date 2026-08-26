<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class QrCodeReceived
{
    public function __construct(public string $code)
    {
    }
}
