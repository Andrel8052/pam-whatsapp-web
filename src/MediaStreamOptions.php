<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MediaStreamOptions
{
    public function __construct(public int $chunkSize = 10 * 1024 * 1024)
    {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('Media stream chunk size must be positive.');
        }
    }
}
