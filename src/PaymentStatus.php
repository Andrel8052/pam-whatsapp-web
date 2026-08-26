<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum PaymentStatus: int
{
    case Unknown = 1;
    case Processing = 2;
    case Sent = 3;
    case NeedToAccept = 4;
    case Complete = 5;
    case CouldNotComplete = 6;
    case Refunded = 7;
    case Expired = 8;
    case Rejected = 9;
    case Cancelled = 10;
    case WaitingForPayer = 11;
    case Waiting = 12;
}
