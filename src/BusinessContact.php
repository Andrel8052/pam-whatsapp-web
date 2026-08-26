<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;

final class BusinessContact extends Contact
{
    public readonly BusinessProfile $businessProfile;

    /** @param array<string, mixed> $payload */
    public function __construct(Session $session, array $payload)
    {
        parent::__construct($session, $payload);
        $this->businessProfile = new BusinessProfile(
            Payload::object($payload['businessProfile'] ?? null, 'Business profile'),
        );
    }
}
