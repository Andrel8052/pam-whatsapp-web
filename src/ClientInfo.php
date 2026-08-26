<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Event\BatteryChanged;
use Pam\WhatsApp\Support\Payload;

final readonly class ClientInfo
{
    public ContactId $me;
    public ContactId $wid;
    public ?ClientInfoPhone $phone;
    public string $platform;
    public string $pushname;

    /** @param array<string, mixed> $payload */
    public function __construct(private Session $session, array $payload)
    {
        $this->wid = ContactId::fromPayload(Payload::object($payload['wid'] ?? null, 'Current user id'));
        $this->me = $this->wid;
        $phone = $payload['phone'] ?? null;
        $this->phone = is_array($phone) ? new ClientInfoPhone(Payload::object($phone, 'Client phone')) : null;
        $this->platform = Payload::string($payload, 'platform');
        $this->pushname = Payload::string($payload, 'pushname');
    }

    public function getBatteryStatus(): BatteryChanged
    {
        $payload = Payload::object($this->session->invoke('getBatteryStatus'), 'Battery status');

        return new BatteryChanged(Payload::int($payload, 'battery'), Payload::bool($payload, 'plugged'));
    }
}
