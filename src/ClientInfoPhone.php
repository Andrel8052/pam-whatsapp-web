<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class ClientInfoPhone
{
    public string $wa_version;
    public string $os_version;
    public string $device_manufacturer;
    public string $device_model;
    public string $os_build_number;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->wa_version = Payload::string($payload, 'wa_version');
        $this->os_version = Payload::string($payload, 'os_version');
        $this->device_manufacturer = Payload::string($payload, 'device_manufacturer');
        $this->device_model = Payload::string($payload, 'device_model');
        $this->os_build_number = Payload::string($payload, 'os_build_number');
    }
}
