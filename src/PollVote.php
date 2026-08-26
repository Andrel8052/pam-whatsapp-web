<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;

final readonly class PollVote
{
    /** @var list<SelectedPollOption> */
    public array $selectedOptions;

    /** @param array<string, mixed> $payload */
    public function __construct(Session $session, array $payload)
    {
        $this->voter = Payload::string($payload, 'voter');
        $this->selectedOptions = array_map(
            static fn (array $option): SelectedPollOption => SelectedPollOption::fromPayload($option),
            Payload::objects($payload['selectedOptions'] ?? [], 'Selected poll options'),
        );
        $this->interractedAtTs = Payload::int($payload, 'interractedAtTs');
        $parent = Payload::object($payload['parentMessage'] ?? null, 'Poll parent message');
        $this->parentMessage = new Message($session, MessageData::fromPayload($parent));
    }

    public string $voter;
    public int $interractedAtTs;
    public Message $parentMessage;
}
