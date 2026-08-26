<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class BusinessProfile
{
    public ContactId $id;
    public string $tag;
    public string $description;

    /** @var list<BusinessCategory> */
    public array $categories;

    /** @var array<string, mixed> */
    public array $profileOptions;

    /** @var list<string> */
    public array $website;

    public string $email;
    public float $latitude;
    public float $longitude;
    public ?BusinessHours $businessHours;
    public string $address;

    /** @var array<string, mixed> */
    public array $fbPage;

    public bool $ifProfileLinked;
    public mixed $coverPhoto;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = ContactId::fromPayload(Payload::object($payload['id'] ?? null, 'Business profile id'));
        $this->tag = Payload::string($payload, 'tag');
        $this->description = Payload::string($payload, 'description');
        $this->categories = array_map(
            static fn (array $category): BusinessCategory => new BusinessCategory($category),
            Payload::objects($payload['categories'] ?? [], 'Business categories'),
        );
        $this->profileOptions = Payload::object($payload['profileOptions'] ?? [], 'Business profile options');
        $websites = $payload['website'] ?? [];
        $this->website = is_array($websites) ? array_values(array_filter($websites, 'is_string')) : [];
        $this->email = Payload::string($payload, 'email');
        $this->latitude = self::number($payload['latitude'] ?? 0.0);
        $this->longitude = self::number($payload['longitude'] ?? 0.0);
        $hours = $payload['businessHours'] ?? null;
        $this->businessHours = is_array($hours) ? new BusinessHours(Payload::object($hours, 'Business hours')) : null;
        $this->address = Payload::string($payload, 'address');
        $this->fbPage = Payload::object($payload['fbPage'] ?? [], 'Business Facebook page');
        $this->ifProfileLinked = Payload::bool($payload, 'ifProfileLinked');
        $this->coverPhoto = $payload['coverPhoto'] ?? null;
    }

    private static function number(mixed $value): float
    {
        return is_int($value) || is_float($value) ? (float) $value : 0.0;
    }
}
