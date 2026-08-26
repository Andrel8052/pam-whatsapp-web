<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum DisconnectionReason: int
{
    case Logout = 1;
    case Conflict = 2;
    case Unlaunched = 3;
    case QrRetryLimit = 4;
}
