<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum GroupNotificationType: int
{
    case Add = 1;
    case Announce = 2;
    case Demote = 3;
    case Description = 4;
    case Invite = 5;
    case Leave = 6;
    case Picture = 7;
    case Promote = 8;
    case Remove = 9;
    case Restrict = 10;
    case Subject = 11;
}
