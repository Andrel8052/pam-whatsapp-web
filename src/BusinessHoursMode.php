<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum BusinessHoursMode: int
{
    case Closed = 1;
    case Open24Hours = 2;
    case SpecificHours = 3;
    case Unknown = 4;
}
