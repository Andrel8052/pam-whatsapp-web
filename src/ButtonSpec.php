<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class ButtonSpec
{
    public function __construct(
        public string $buttonId,
        public ButtonText $buttonText,
        public int $type,
    ) {
    }
}
