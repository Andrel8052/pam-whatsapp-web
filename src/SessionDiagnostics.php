<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class SessionDiagnostics
{
    /** @param list<string> $errors */
    public function __construct(
        public ClientState $clientState,
        public bool $browserAvailable,
        public ?ConnectionState $connectionState,
        public ?string $webVersion,
        public ?string $accountId,
        public array $errors = [],
    ) {}

    public function healthy(): bool
    {
        return $this->clientState === ClientState::Ready
            && $this->browserAvailable
            && $this->connectionState === ConnectionState::Connected
            && $this->errors === [];
    }
}
