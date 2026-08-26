<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class SendChannelAdminInviteOptions
{
    public function __construct(public ?string $comment = null)
    {
    }

    /** @return array{comment?: string} */
    public function toBridge(): array
    {
        return $this->comment === null ? [] : ['comment' => $this->comment];
    }
}
