<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum MembershipRequestAction: int
{
    case Approve = 1;
    case Reject = 2;
}
