<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\Call;

final readonly class CallReceived
{
    public string $id;
    public string $peerId;
    public int $timestamp;
    public bool $isVideo;
    public bool $isGroup;
    public bool $canHandleLocally;
    public bool $outgoing;
    public bool $webClientShouldHandle;

    /** @var list<string> */
    public array $participantIds;

    public function __construct(public Call $call)
    {
        $this->id = $call->id;
        $this->peerId = $call->from;
        $this->timestamp = $call->timestamp;
        $this->isVideo = $call->isVideo;
        $this->isGroup = $call->isGroup;
        $this->canHandleLocally = $call->canHandleLocally;
        $this->outgoing = $call->fromMe;
        $this->webClientShouldHandle = $call->webClientShouldHandle;
        $this->participantIds = $call->participants;
    }
}
