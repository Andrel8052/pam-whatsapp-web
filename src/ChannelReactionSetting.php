<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum ChannelReactionSetting: int
{
    case None = 1;
    case Basic = 2;
    case All = 3;
}
