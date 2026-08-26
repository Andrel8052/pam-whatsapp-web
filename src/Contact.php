<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Contract\MessageContent;
use Pam\WhatsApp\Support\Payload;

class Contact implements MessageContent
{
    public readonly ContactId $id;
    public readonly string $number;
    public readonly string $name;
    public readonly string $pushname;
    public readonly bool $isBusiness;
    public readonly bool $isEnterprise;
    public readonly bool $isGroup;
    public readonly bool $isMe;
    public readonly bool $isMyContact;
    public readonly bool $isUser;
    public private(set) bool $isBlocked;
    public readonly bool $isWAContact;

    /** @var list<string> */
    public readonly array $labels;

    public readonly ?string $sectionHeader;
    public readonly ?string $shortName;
    public readonly bool $statusMute;
    public readonly ContactType $type;
    public readonly VerifiedLevel $verifiedLevel;
    public readonly ?string $verifiedName;

    /** @param array<string, mixed> $payload */
    public function __construct(private readonly Session $session, array $payload)
    {
        $this->id = ContactId::fromValue($payload['id'] ?? null);
        $this->number = Payload::string($payload, 'number', Payload::string($payload, 'userid'));
        $this->name = Payload::string($payload, 'name');
        $this->pushname = Payload::string($payload, 'pushname');
        $this->isBusiness = Payload::bool($payload, 'isBusiness');
        $this->isEnterprise = Payload::bool($payload, 'isEnterprise');
        $this->isGroup = Payload::bool($payload, 'isGroup');
        $this->isMe = Payload::bool($payload, 'isMe');
        $this->isMyContact = Payload::bool($payload, 'isMyContact');
        $this->isUser = Payload::bool($payload, 'isUser');
        $this->isBlocked = Payload::bool($payload, 'isBlocked');
        $this->isWAContact = Payload::bool($payload, 'isWAContact');
        $labels = $payload['labels'] ?? [];
        $this->labels = is_array($labels) ? array_values(array_filter($labels, 'is_string')) : [];
        $this->sectionHeader = $this->optionalString($payload, 'sectionHeader');
        $this->shortName = $this->optionalString($payload, 'shortName');
        $this->statusMute = Payload::bool($payload, 'statusMute');
        $this->type = ContactType::tryFrom(Payload::int($payload, 'type')) ?? ContactType::Unknown;
        $this->verifiedLevel = VerifiedLevel::tryFrom(Payload::int($payload, 'verifiedLevel')) ?? VerifiedLevel::Unknown;
        $this->verifiedName = $this->optionalString($payload, 'verifiedName');
    }

    public function getProfilePicUrl(): ?string
    {
        $value = $this->session->invoke('getProfilePicUrl', [$this->id->serialized]);

        return is_string($value) ? $value : null;
    }

    public function getFormattedNumber(): string
    {
        return Payload::string(['value' => $this->session->invoke('getFormattedNumber', [$this->id->serialized])], 'value');
    }

    public function getCountryCode(): string
    {
        return Payload::string(['value' => $this->session->invoke('getCountryCode', [$this->id->serialized])], 'value');
    }

    public function getChat(): ?Chat
    {
        if ($this->isMe) return null;

        $chat = \Pam\WhatsApp\Support\ChatFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getChatById', [$this->id->serialized]), 'Contact chat'),
        );

        if ($chat instanceof Channel) {
            throw new \UnexpectedValueException('A contact chat cannot be a channel.');
        }

        return $chat;
    }

    public function block(): bool
    {
        if ($this->isGroup) return false;
        $this->session->invoke('blockContact', [$this->id->serialized, true]);
        $this->isBlocked = true;

        return true;
    }

    public function unblock(): bool
    {
        if ($this->isGroup) return false;
        $this->session->invoke('blockContact', [$this->id->serialized, false]);
        $this->isBlocked = false;

        return true;
    }

    public function getAbout(): ?string
    {
        $value = $this->session->invoke('getContactAbout', [$this->id->serialized]);

        return is_string($value) ? $value : null;
    }

    /** @return list<ContactId> */
    public function getCommonGroups(): array
    {
        return array_map(
            static fn (array $group): ContactId => ContactId::fromPayload($group),
            Payload::objects($this->session->invoke('getCommonGroups', [$this->id->serialized]), 'Common groups'),
        );
    }

    public function getBroadcast(): ?Broadcast
    {
        $value = $this->session->invoke('getBroadcastById', [$this->id->serialized]);

        return $value === null ? null : new Broadcast($this->session, Payload::object($value, 'Contact broadcast'));
    }

    public function toBridge(): array
    {
        return ['kind' => ContentKind::Contact->value, 'contactId' => $this->id->serialized];
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
