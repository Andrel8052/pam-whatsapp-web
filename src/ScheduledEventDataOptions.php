<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class ScheduledEventDataOptions
{
    /** @param list<int>|null $messageSecret */
    public function __construct(
        public ?string $description,
        public ?int $endTimeTs,
        public ?string $location,
        public ScheduledEventCallType $callType,
        public bool $isEventCanceled,
        public ?array $messageSecret,
    ) {
    }
}
