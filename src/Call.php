<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Exception\BridgeException;
use Pam\WhatsApp\Support\Payload;

final readonly class Call
{
    public string $id;
    public string $from;
    public int $timestamp;
    public bool $isVideo;
    public bool $isGroup;
    public bool $fromMe;
    public bool $canHandleLocally;
    public bool $webClientShouldHandle;

    /** @var list<string> */
    public array $participants;

    /** @param array<string, mixed> $payload */
    public function __construct(private Session $session, array $payload)
    {
        $this->id = Payload::string($payload, 'id');
        $this->from = Payload::string($payload, 'from', Payload::string($payload, 'peerId'));
        $this->timestamp = Payload::int($payload, 'timestamp');
        $this->isVideo = Payload::bool($payload, 'isVideo');
        $this->isGroup = Payload::bool($payload, 'isGroup');
        $this->fromMe = Payload::bool($payload, 'fromMe') || Payload::bool($payload, 'outgoing');
        $this->canHandleLocally = Payload::bool($payload, 'canHandleLocally');
        $this->webClientShouldHandle = Payload::bool($payload, 'webClientShouldHandle');
        $participants = $payload['participants'] ?? $payload['participantIds'] ?? [];
        if (!is_array($participants)) {
            throw new BridgeException('Call participants must be a list.');
        }
        $normalized = [];
        foreach ($participants as $participant) {
            if (!is_string($participant)) {
                throw new BridgeException('Call participant ids must be strings.');
            }
            $normalized[] = $participant;
        }
        $this->participants = $normalized;
    }

    public function reject(): void
    {
        $this->session->invoke('rejectCall', [$this->from, $this->id]);
    }
}
