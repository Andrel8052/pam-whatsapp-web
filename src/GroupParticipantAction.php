<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum GroupParticipantAction: int
{
    case Remove = 1;
    case Promote = 2;
    case Demote = 3;
}
