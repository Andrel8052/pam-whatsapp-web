<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class LocalWebCacheOptions implements WebVersionCacheOptions
{
    public WebCacheType $type;

    public function __construct(
        public ?string $path = null,
        public bool $strict = false,
    ) {
        $this->type = WebCacheType::Local;
    }
}
