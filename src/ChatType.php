<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ChatType: int
{
    case Group = 1;
    case Solo = 2;
    case Unknown = 3;
}
