<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum MembershipRequestMethod: int
{
    case NonAdminAdd = 1;
    case InviteLink = 2;
    case LinkedGroupJoin = 3;
    case Unknown = 4;
}
