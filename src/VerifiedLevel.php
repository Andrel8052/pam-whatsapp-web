<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum VerifiedLevel: int
{
    case Unknown = 1;
    case Low = 2;
    case High = 3;
}
