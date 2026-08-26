<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageReceipt
{
    public function __construct(public ContactId $id, public int $timestamp)
    {
    }
}
