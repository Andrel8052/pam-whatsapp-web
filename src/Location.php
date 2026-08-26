<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageContent;

final readonly class Location implements MessageContent
{
    public string $description;
    public ?string $name;
    public ?string $address;
    public ?string $url;

    public function __construct(
        public float $latitude,
        public float $longitude,
        ?LocationSendOptions $options = null,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException('Location coordinates are outside valid bounds.');
        }
        $options ??= new LocationSendOptions();
        $this->name = $options->name;
        $this->address = $options->address;
        $this->url = $options->url;
        $this->description = implode("\n", array_filter([$this->name, $this->address], static fn (?string $v): bool => $v !== null && $v !== ''));
    }

    public function toBridge(): array
    {
        return [
            'kind' => ContentKind::Location->value,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'description' => $this->description,
            'url' => $this->url,
        ];
    }
}
