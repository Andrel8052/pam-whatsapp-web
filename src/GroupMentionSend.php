<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class GroupMentionSend
{
    public function __construct(public string $subject, public string $id)
    {
        if ($subject === '' || $id === '') {
            throw new \InvalidArgumentException('Group mention subject and id must be non-empty.');
        }
    }
}
