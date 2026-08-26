<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Exception;

final class ContactNotFoundException extends WhatsAppException
{
    public function __construct(public readonly string $phoneNumber)
    {
        parent::__construct(sprintf('Phone number %s is not registered on WhatsApp.', $phoneNumber));
    }
}
