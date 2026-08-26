<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class PollSendOptions
{
    /** @param list<int>|null $messageSecret */
    public function __construct(
        public bool $allowMultipleAnswers = false,
        public ?array $messageSecret = null,
    ) {
        if ($messageSecret !== null && count($messageSecret) !== 32) {
            throw new \InvalidArgumentException('Poll message secret must contain 32 bytes.');
        }
    }
}
