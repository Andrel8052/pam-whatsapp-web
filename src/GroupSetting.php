<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum GroupSetting: int
{
    case AddMembers = 1;
    case Messages = 2;
    case Info = 3;
}
