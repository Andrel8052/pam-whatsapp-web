<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Contract;

use Pam\WhatsApp\Bridge\BridgeEvent;

interface Session
{
    /** @param callable(BridgeEvent): void $listener */
    public function initialize(callable $listener): void;

    public function sendText(string $chatId, string $body, ?string $quotedMessageId = null): MessageData;

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $options
     */
    public function sendContent(string $chatId, array $content, array $options = []): MessageData;

    /** @param list<mixed> $arguments */
    public function invoke(string $method, array $arguments = []): mixed;

    public function pump(float $timeoutSeconds): bool;

    public function logout(): void;

    public function close(): void;
}
