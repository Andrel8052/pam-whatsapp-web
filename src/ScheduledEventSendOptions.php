<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use DateTimeImmutable;

final readonly class ScheduledEventSendOptions
{
    /** @param list<int>|null $messageSecret */
    public function __construct(
        public ?string $description = null,
        public ?DateTimeImmutable $endTime = null,
        public ?string $location = null,
        public ScheduledEventCallType $callType = ScheduledEventCallType::None,
        public bool $isEventCanceled = false,
        public ?array $messageSecret = null,
    ) {
        if ($messageSecret !== null && count($messageSecret) !== 32) {
            throw new \InvalidArgumentException('Scheduled event message secret must contain 32 bytes.');
        }
    }
}
