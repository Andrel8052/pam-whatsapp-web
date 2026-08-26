<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ChannelSubscriberRole: int
{
    case Owner = 1;
    case Admin = 2;
    case Subscriber = 3;
    case Unknown = 4;
}
