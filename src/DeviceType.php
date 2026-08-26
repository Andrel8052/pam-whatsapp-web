<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum DeviceType: int
{
    case Android = 1;
    case Ios = 2;
    case Web = 3;
    case Unknown = 4;
}
