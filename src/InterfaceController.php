<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Exception\BridgeException;
use Pam\WhatsApp\Support\Payload;

final readonly class InterfaceController
{
    public function __construct(private Session $session)
    {
    }

    public function openChatWindow(string $chatId): mixed
    {
        return $this->session->invoke('openChatWindow', [$this->identifier($chatId, 'Chat')]);
    }

    public function openChatDrawer(string $chatId): void
    {
        $this->session->invoke('openChatDrawer', [$this->identifier($chatId, 'Chat')]);
    }

    public function openChatSearch(string $chatId): void
    {
        $this->session->invoke('openChatSearch', [$this->identifier($chatId, 'Chat')]);
    }

    public function openChatWindowAt(string $messageId): void
    {
        $this->session->invoke('openChatWindowAt', [$this->identifier($messageId, 'Message')]);
    }

    public function openMessageDrawer(string $messageId): void
    {
        $this->session->invoke('openMessageDrawer', [$this->identifier($messageId, 'Message')]);
    }

    public function closeRightDrawer(): void
    {
        $this->session->invoke('closeRightDrawer');
    }

    /** @return array<string, mixed> */
    public function getFeatures(): array
    {
        return Payload::object($this->session->invoke('getFeatures'), 'WhatsApp features');
    }

    public function checkFeatureStatus(string $feature): bool
    {
        $value = $this->session->invoke('checkFeatureStatus', [$this->identifier($feature, 'Feature')]);
        if (!is_bool($value)) {
            throw new BridgeException('WhatsApp feature status must be boolean.');
        }

        return $value;
    }

    /** @param list<string> $features */
    public function enableFeatures(array $features): void
    {
        $this->session->invoke('enableFeatures', [$this->features($features)]);
    }

    /** @param list<string> $features */
    public function disableFeatures(array $features): void
    {
        $this->session->invoke('disableFeatures', [$this->features($features)]);
    }

    private function identifier(string $value, string $label): string
    {
        if ($value === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException($label.' identifier must be non-empty and contain no NUL bytes.');
        }

        return $value;
    }

    /**
     * @param list<string> $features
     * @return list<string>
     */
    private function features(array $features): array
    {
        foreach ($features as $feature) {
            $this->identifier($feature, 'Feature');
        }

        return $features;
    }
}
