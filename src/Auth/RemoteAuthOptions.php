<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

final readonly class RemoteAuthOptions
{
    public function __construct(
        public RemoteStore $store,
        public int $backupSyncIntervalMs,
        public ?string $clientId = null,
        public ?string $dataPath = null,
        public int $rmMaxRetries = 4,
        public ?\Closure $clock = null,
    ) {}
}
