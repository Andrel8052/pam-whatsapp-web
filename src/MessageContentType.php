<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum MessageContentType: int
{
    case Text = 1;
    case Media = 2;
    case Location = 3;
    case Contact = 4;
    case Poll = 5;
    case System = 6;
    case Unknown = 7;
    case Order = 8;
    case Payment = 9;
}
