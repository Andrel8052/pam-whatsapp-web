<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

final readonly class Authenticated
{
    public function __construct(
        public int $timestamp,
        public mixed $authPayload = null,
    ) {
    }
}
