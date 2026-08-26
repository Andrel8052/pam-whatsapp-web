<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class BusinessHours
{
    /** @var array{sun: BusinessHoursOfDay, mon: BusinessHoursOfDay, tue: BusinessHoursOfDay, wed: BusinessHoursOfDay, thu: BusinessHoursOfDay, fri: BusinessHoursOfDay} */
    public array $config;
    public string $timezone;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $config = Payload::object($payload['config'] ?? [], 'Business hours config');
        $this->config = [
            'sun' => self::day($config, 'sun'),
            'mon' => self::day($config, 'mon'),
            'tue' => self::day($config, 'tue'),
            'wed' => self::day($config, 'wed'),
            'thu' => self::day($config, 'thu'),
            'fri' => self::day($config, 'fri'),
        ];
        $this->timezone = Payload::string($payload, 'timezone');
    }

    /** @param array<string, mixed> $config */
    private static function day(array $config, string $day): BusinessHoursOfDay
    {
        return new BusinessHoursOfDay(Payload::object($config[$day] ?? [], "Business hours {$day}"));
    }
}
