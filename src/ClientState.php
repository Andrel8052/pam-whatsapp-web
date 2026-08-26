<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ClientState: int
{
    case Created = 1;
    case Initializing = 2;
    case AwaitingAuthentication = 3;
    case Ready = 4;
    case Closed = 5;
    case Failed = 6;
}
