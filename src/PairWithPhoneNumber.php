<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class PairWithPhoneNumber
{
    public function __construct(
        public string $phoneNumber,
        public bool $showNotification = true,
        public int $intervalMs = 180_000,
    ) {
        if ($phoneNumber === '' || preg_match('/^\d+$/', $phoneNumber) !== 1) {
            throw new \InvalidArgumentException('Pairing phone number must contain digits only.');
        }
        if ($intervalMs <= 0) {
            throw new \InvalidArgumentException('Pairing interval must be positive.');
        }
    }
}
