<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum WebCacheType: int
{
    case None = 1;
    case Local = 2;
    case Remote = 3;
}
