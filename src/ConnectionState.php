<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ConnectionState: int
{
    case Connected = 1;
    case Opening = 2;
    case Pairing = 3;
    case Timeout = 4;
    case Unlaunched = 5;
    case Unpaired = 6;
    case UnpairedIdle = 7;
    case Conflict = 8;
    case DeprecatedVersion = 9;
    case ProxyBlock = 10;
    case TosBlock = 11;
    case SmbaTosBlock = 12;
    case Unknown = 13;
}
