<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use DateTimeImmutable;
use DateTimeInterface;
use Pam\WhatsApp\Contract\MessageContent;

final readonly class ScheduledEvent implements MessageContent
{
    public ?string $description;
    public ?DateTimeImmutable $endTime;
    public ?string $location;
    public ScheduledEventCallType $callType;
    public bool $isEventCanceled;

    /** @var list<int>|null */
    public ?array $messageSecret;
    public int $startTimeTs;
    public ScheduledEventDataOptions $eventSendOptions;

    public function __construct(
        public string $name,
        public DateTimeInterface $startTime,
        ?ScheduledEventSendOptions $options = null,
    ) {
        $options ??= new ScheduledEventSendOptions();
        if ($name === '') {
            throw new \InvalidArgumentException('Scheduled event name cannot be empty.');
        }
        if ($options->endTime !== null && $options->endTime <= $startTime) {
            throw new \InvalidArgumentException('Scheduled event end must be after its start.');
        }
        if ($options->messageSecret !== null && count($options->messageSecret) !== 32) {
            throw new \InvalidArgumentException('Scheduled event message secret must contain 32 bytes.');
        }
        $this->description = $options->description;
        $this->endTime = $options->endTime;
        $this->location = $options->location;
        $this->callType = $options->callType;
        $this->isEventCanceled = $options->isEventCanceled;
        $this->messageSecret = $options->messageSecret;
        $this->startTimeTs = $startTime->getTimestamp();
        $this->eventSendOptions = new ScheduledEventDataOptions(
            $options->description,
            $options->endTime?->getTimestamp(),
            $options->location,
            $options->callType,
            $options->isEventCanceled,
            $options->messageSecret,
        );
    }

    public function toBridge(): array
    {
        return [
            'kind' => ContentKind::ScheduledEvent->value,
            'name' => $this->name,
            'startTime' => $this->startTime->getTimestamp(),
            'description' => $this->description,
            'endTime' => $this->endTime?->getTimestamp(),
            'location' => $this->location,
            'callType' => $this->callType->value,
            'isEventCanceled' => $this->isEventCanceled,
            'messageSecret' => $this->messageSecret,
        ];
    }
}
