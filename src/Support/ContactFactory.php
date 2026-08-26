<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Support;

use Pam\WhatsApp\BusinessContact;
use Pam\WhatsApp\Contact;
use Pam\WhatsApp\Contract\Session;

final class ContactFactory
{
    /** @param array<string, mixed> $payload */
    public static function make(Session $session, array $payload): Contact
    {
        return Payload::bool($payload, 'isBusiness') && is_array($payload['businessProfile'] ?? null)
            ? new BusinessContact($session, $payload)
            : new Contact($session, $payload);
    }
}
