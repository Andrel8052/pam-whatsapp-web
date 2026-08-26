<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum SupportedFeatureStatus: int
{
    case Supported = 1;
    case DeprecatedUpstream = 2;
    case PlannedUpstream = 3;
}
