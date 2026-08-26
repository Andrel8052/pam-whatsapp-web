<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class ClientError
{
    public function __construct(public string $message)
    {
    }
}
