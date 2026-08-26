<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class ContactLidAndPhone
{
    public string $lid;
    public string $pn;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->lid = Payload::string($payload, 'lid');
        $this->pn = Payload::string($payload, 'pn');
    }
}
