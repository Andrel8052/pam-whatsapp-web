<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Exception\BridgeException;
use Pam\WhatsApp\Support\Payload;
use Pam\WhatsApp\Support\ContactFactory;

final readonly class ChannelSubscriber
{
    public Contact $contact;
    public ChannelSubscriberRole $role;

    /** @param array<string, mixed> $payload */
    public function __construct(Session $session, array $payload)
    {
        $this->contact = ContactFactory::make($session, Payload::object($payload['contact'] ?? null, 'Channel subscriber contact'));
        $role = $payload['role'] ?? null;
        if (!is_int($role) || ChannelSubscriberRole::tryFrom($role) === null) {
            throw new BridgeException('Channel subscriber role must be a known integer.');
        }
        $this->role = ChannelSubscriberRole::from($role);
    }
}
