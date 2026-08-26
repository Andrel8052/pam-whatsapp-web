<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;

final class Channel
{
    public readonly ContactId $id;
    public private(set) string $name;
    public private(set) string $description;
    public readonly bool $isChannel;
    public readonly bool $isGroup;
    public readonly bool $isReadOnly;
    public readonly int $unreadCount;
    public readonly int $timestamp;
    public private(set) bool $isMuted;
    public private(set) int $muteExpiration;
    public readonly ?Message $lastMessage;

    /** @param array<string, mixed> $payload */
    public function __construct(private readonly Session $session, array $payload)
    {
        $this->id = ContactId::fromValue($payload['id'] ?? null);
        $this->name = Payload::string($payload, 'name');
        $metadata = is_array($payload['channelMetadata'] ?? null) ? $payload['channelMetadata'] : [];
        $this->description = Payload::string($metadata, 'description', Payload::string($payload, 'description'));
        $this->isChannel = Payload::bool($payload, 'isChannel');
        $this->isGroup = Payload::bool($payload, 'isGroup');
        $this->isReadOnly = Payload::bool($payload, 'isReadOnly');
        $this->unreadCount = Payload::int($payload, 'unreadCount');
        $this->timestamp = Payload::int($payload, 't');
        $this->isMuted = Payload::bool($payload, 'isMuted');
        $this->muteExpiration = Payload::int($payload, 'muteExpiration');
        $lastMessage = $payload['lastMessage'] ?? null;
        $this->lastMessage = $lastMessage === null
            ? null
            : new Message($session, MessageData::fromPayload(Payload::object($lastMessage, 'Last channel message')));
    }

    /** @return list<ChannelSubscriber> */
    public function getSubscribers(?int $limit = null): array
    {
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('Channel subscriber limit must be positive.');
        }

        return array_map(
            fn (array $payload): ChannelSubscriber => new ChannelSubscriber($this->session, $payload),
            Payload::objects($this->session->invoke('getChannelSubscribers', [$this->id->serialized, $limit]), 'Channel subscribers'),
        );
    }

    public function setSubject(string $newSubject): bool
    {
        $success = $this->session->invoke('setChannelMetadata', [$this->id->serialized, ['name' => $newSubject], ['editName' => true]]) === true;
        if ($success) {
            $this->name = $newSubject;
        }

        return $success;
    }

    public function setDescription(string $newDescription): bool
    {
        $success = $this->session->invoke('setChannelMetadata', [$this->id->serialized, ['description' => $newDescription], ['editDescription' => true]]) === true;
        if ($success) {
            $this->description = $newDescription;
        }

        return $success;
    }

    public function setProfilePicture(MessageMedia $newProfilePicture): bool
    {
        return $this->session->invoke('setChannelMetadata', [
            $this->id->serialized,
            ['picture' => $newProfilePicture->toBridge()['media']],
            ['editPicture' => true],
        ]) === true;
    }

    public function setReactionSetting(ChannelReactionSetting $reactionCode): bool
    {
        return $this->session->invoke('setChannelReactionSetting', [$this->id->serialized, $reactionCode->value]) === true;
    }

    public function mute(): bool
    {
        $success = $this->session->invoke('muteChannel', [$this->id->serialized, true]) === true;
        if ($success) {
            $this->isMuted = true;
            $this->muteExpiration = -1;
        }

        return $success;
    }

    public function unmute(): bool
    {
        $success = $this->session->invoke('muteChannel', [$this->id->serialized, false]) === true;
        if ($success) {
            $this->isMuted = false;
            $this->muteExpiration = 0;
        }

        return $success;
    }

    public function sendMessage(string|MessageMedia $content, ?MessageSendChannelOptions $options = null): Message
    {
        $payload = is_string($content)
            ? ['kind' => ContentKind::Text->value, 'text' => $content]
            : $content->toBridge();

        return new Message($this->session, $this->session->sendContent($this->id->serialized, $payload, $options?->toBridge() ?? []));
    }

    public function sendSeen(): bool
    {
        return $this->session->invoke('sendSeen', [$this->id->serialized]) === true;
    }

    public function sendChannelAdminInvite(string $chatId, ?SendChannelAdminInviteOptions $options = null): bool
    {
        return $this->session->invoke('sendChannelAdminInvite', [$chatId, $this->id->serialized, $options?->toBridge() ?? []]) === true;
    }

    public function acceptChannelAdminInvite(): bool
    {
        return $this->session->invoke('acceptChannelAdminInvite', [$this->id->serialized]) === true;
    }

    public function revokeChannelAdminInvite(string $userId): bool
    {
        return $this->session->invoke('revokeChannelAdminInvite', [$this->id->serialized, $userId]) === true;
    }

    public function demoteChannelAdmin(string $userId): bool
    {
        return $this->session->invoke('demoteChannelAdmin', [$this->id->serialized, $userId]) === true;
    }

    public function transferChannelOwnership(string $newOwnerId, ?TransferChannelOwnershipOptions $options = null): bool
    {
        return $this->session->invoke('transferChannelOwnership', [$this->id->serialized, $newOwnerId, $options?->toBridge() ?? []]) === true;
    }

    /** @return list<Message> */
    public function fetchMessages(?MessageSearchOptions $searchOptions = null): array
    {
        return array_map(
            fn (array $payload): Message => new Message($this->session, MessageData::fromPayload($payload)),
            Payload::objects($this->session->invoke('fetchChannelMessages', [$this->id->serialized, $searchOptions?->toBridge() ?? []]), 'Channel messages'),
        );
    }

    public function deleteChannel(): bool
    {
        return $this->session->invoke('deleteChannel', [$this->id->serialized]) === true;
    }
}
