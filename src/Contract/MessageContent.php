<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Contract;

interface MessageContent
{
    /** @return array<string, mixed> */
    public function toBridge(): array;
}
