<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class TransferChannelOwnershipOptions
{
    public function __construct(public bool $shouldDismissSelfAsAdmin = false)
    {
    }

    /** @return array{shouldDismissSelfAsAdmin: bool} */
    public function toBridge(): array
    {
        return ['shouldDismissSelfAsAdmin' => $this->shouldDismissSelfAsAdmin];
    }
}
