<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class RemoteWebCacheOptions implements WebVersionCacheOptions
{
    public WebCacheType $type;

    public function __construct(
        public string $remotePath,
        public bool $strict = false,
        public float $timeoutSeconds = 10.0,
    ) {
        if ($remotePath === '' || $timeoutSeconds <= 0.0) {
            throw new \InvalidArgumentException('Remote web cache path cannot be empty.');
        }
        $this->type = WebCacheType::Remote;
    }
}
