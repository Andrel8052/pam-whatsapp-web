<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class NoWebCacheOptions implements WebVersionCacheOptions
{
    public WebCacheType $type;

    public function __construct()
    {
        $this->type = WebCacheType::None;
    }
}
