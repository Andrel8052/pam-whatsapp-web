<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ScheduledEventResponse: int
{
    case None = 1;
    case Going = 2;
    case NotGoing = 3;
    case Maybe = 4;
}
