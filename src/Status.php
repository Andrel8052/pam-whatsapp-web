<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum Status: int
{
    case Initializing = 1;
    case Authenticating = 2;
    case Ready = 3;
}
