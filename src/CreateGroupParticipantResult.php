<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class CreateGroupParticipantResult
{
    public int $statusCode;
    public string $message;
    public bool $isGroupCreator;
    public bool $isInviteV4Sent;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->statusCode = Payload::int($payload, 'statusCode');
        $this->message = Payload::string($payload, 'message');
        $this->isGroupCreator = Payload::bool($payload, 'isGroupCreator');
        $this->isInviteV4Sent = Payload::bool($payload, 'isInviteV4Sent');
    }
}
