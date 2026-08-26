<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class GroupMention
{
    public function __construct(public string $groupSubject, public string $groupJid)
    {
    }
}
