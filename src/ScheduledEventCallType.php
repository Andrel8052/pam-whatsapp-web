<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ScheduledEventCallType: int
{
    case None = 1;
    case Voice = 2;
    case Video = 3;
}
