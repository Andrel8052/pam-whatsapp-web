<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class RemoteSessionSaved
{
    public function __construct(public int $timestamp)
    {
    }
}
