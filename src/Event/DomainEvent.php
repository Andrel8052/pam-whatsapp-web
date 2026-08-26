<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\EventType;

final readonly class DomainEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(public EventType $type, public array $payload)
    {
    }
}
