<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Exception\BridgeException;
use Pam\WhatsApp\Support\Payload;

final readonly class GroupMembershipRequest
{
    public ContactId $id;
    public ContactId $addedBy;
    public ?ContactId $parentGroupId;
    public MembershipRequestMethod $requestMethod;
    public int $timestamp;
    public int $t;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = ContactId::fromPayload(Payload::object($payload['id'] ?? null, 'Membership requester id'));
        $this->addedBy = ContactId::fromPayload(Payload::object($payload['addedBy'] ?? null, 'Membership added-by id'));
        $parent = $payload['parentGroupId'] ?? null;
        $this->parentGroupId = $parent === null ? null : ContactId::fromPayload(Payload::object($parent, 'Membership parent group id'));
        $method = $payload['requestMethod'] ?? null;
        if (!is_int($method)) {
            throw new BridgeException('Membership request method must be an integer.');
        }
        $this->requestMethod = MembershipRequestMethod::from($method);
        $this->timestamp = Payload::int($payload, 'timestamp');
        $this->t = $this->timestamp;
    }
}
