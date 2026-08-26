<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

use Pam\WhatsApp\Client;

abstract class AuthStrategy
{
    protected ?Client $client = null;

    public function setup(Client $client): void
    {
        $this->client = $client;
    }

    public function prepare(): ?string
    {
        return null;
    }

    public function beforeBrowserInitialized(): void
    {
    }

    public function afterBrowserInitialized(): void
    {
    }

    /** @return array{failed?: bool, restart?: bool, failureEventPayload?: mixed} */
    public function onAuthenticationNeeded(): array
    {
        return [
            'failed' => false,
            'restart' => false,
            'failureEventPayload' => null,
        ];
    }

    public function getAuthEventPayload(): mixed
    {
        return null;
    }

    public function afterAuthReady(): void
    {
    }

    public function onPump(string $profileDirectory): bool
    {
        return false;
    }

    public function disconnect(): void
    {
    }

    public function logout(): void
    {
    }

    public function close(): void
    {
    }

    public function destroy(): void
    {
        $this->close();
        $this->client = null;
    }
}
