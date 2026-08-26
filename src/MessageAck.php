<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum MessageAck: int
{
    case Error = 1;
    case Pending = 2;
    case Server = 3;
    case Device = 4;
    case Read = 5;
    case Played = 6;
}
