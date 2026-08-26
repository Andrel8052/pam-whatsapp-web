<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ContentKind: int
{
    case Text = 1;
    case Media = 2;
    case Location = 3;
    case Poll = 4;
    case Contact = 5;
    case ContactList = 6;
    case ListMessage = 7;
    case Buttons = 8;
    case ScheduledEvent = 9;
}
