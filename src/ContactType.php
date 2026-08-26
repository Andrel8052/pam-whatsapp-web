<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ContactType: int
{
    case Incoming = 1;
    case Outgoing = 2;
    case Unknown = 3;
}
