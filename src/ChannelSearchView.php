<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ChannelSearchView: int
{
    case Recommended = 1;
    case Trending = 2;
    case Popular = 3;
    case New = 4;
}
