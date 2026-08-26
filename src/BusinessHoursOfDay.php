<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Exception\BridgeException;
use Pam\WhatsApp\Support\Payload;

final readonly class BusinessHoursOfDay
{
    public BusinessHoursMode $mode;

    /** @var list<int> */
    public array $hours;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $mode = Payload::int($payload, 'mode');
        $this->mode = BusinessHoursMode::tryFrom($mode) ?? BusinessHoursMode::Unknown;
        $hours = $payload['hours'] ?? [];
        if (!is_array($hours)) {
            throw new BridgeException('Business hours must be an integer list.');
        }
        $normalized = [];
        foreach ($hours as $hour) {
            if (!is_int($hour)) {
                throw new BridgeException('Business hours must be an integer list.');
            }
            $normalized[] = $hour;
        }
        $this->hours = $normalized;
    }
}
