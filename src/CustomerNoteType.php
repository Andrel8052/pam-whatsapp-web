<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum CustomerNoteType: int
{
    case Unstructured = 1;
    case Unknown = 2;
}
