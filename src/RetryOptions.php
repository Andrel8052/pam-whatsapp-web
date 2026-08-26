<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class RetryOptions
{
    public function __construct(
        public int $maxAttempts = 1,
        public int $initialDelayMs = 250,
        public float $multiplier = 2.0,
        public int $maximumDelayMs = 5_000,
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 10) {
            throw new \InvalidArgumentException('Retry attempts must be between 1 and 10.');
        }
        if ($initialDelayMs < 0 || $maximumDelayMs < 0 || $initialDelayMs > $maximumDelayMs) {
            throw new \InvalidArgumentException('Retry delays are invalid.');
        }
        if ($multiplier < 1.0 || !is_finite($multiplier)) {
            throw new \InvalidArgumentException('Retry multiplier must be finite and at least 1.');
        }
    }
}
