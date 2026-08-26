<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class ParticipantActionResult
{
    public function __construct(public int $status)
    {
    }
}
