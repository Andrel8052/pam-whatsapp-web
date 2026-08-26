<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum DeliveryEventType: int
{
    case Sent = 1;
    case Delivered = 2;
    case Read = 3;
    case Failed = 4;
}
