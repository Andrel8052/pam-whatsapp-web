<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class UnsubscribeOptions
{
    public function __construct(public bool $deleteLocalModels = false)
    {
    }

    /** @return array{deleteLocalModels: bool} */
    public function toBridge(): array
    {
        return ['deleteLocalModels' => $this->deleteLocalModels];
    }
}
