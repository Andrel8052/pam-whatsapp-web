<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Contract\MessageContent;
use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Support\Payload;
use Pam\WhatsApp\Support\ContactFactory;

class Chat
{
    public readonly ContactId $id;
    public protected(set) string $name;
    public readonly bool $isGroup;
    public private(set) bool $isMuted;
    public readonly bool $isReadOnly;
    public readonly bool $archived;
    public readonly bool $pinned;
    public readonly bool $isLocked;
    public readonly int $timestamp;
    public readonly int $unreadCount;
    public private(set) int $muteExpiration;
    public readonly ?Message $lastMessage;

    /** @param array<string, mixed> $payload */
    public function __construct(protected readonly Session $session, array $payload)
    {
        $this->id = ContactId::fromValue($payload['id'] ?? null);
        $this->name = Payload::string($payload, 'formattedTitle', Payload::string($payload, 'name'));
        $this->isGroup = Payload::bool($payload, 'isGroup');
        $this->isMuted = Payload::bool($payload, 'isMuted');
        $this->isReadOnly = Payload::bool($payload, 'isReadOnly');
        $this->archived = Payload::bool($payload, 'archive');
        $this->pinned = Payload::bool($payload, 'pin');
        $this->isLocked = Payload::bool($payload, 'isLocked');
        $this->timestamp = Payload::int($payload, 't');
        $this->unreadCount = Payload::int($payload, 'unreadCount');
        $this->muteExpiration = Payload::int($payload, 'muteExpiration');
        $lastMessage = $payload['lastMessage'] ?? null;
        $this->lastMessage = $lastMessage === null
            ? null
            : new Message($session, MessageData::fromPayload(Payload::object($lastMessage, 'Last message')));
    }

    public function sendMessage(
        string|MessageContent $content,
        ?MessageSendOptions $options = null,
    ): Message
    {
        $payload = is_string($content)
            ? ['kind' => ContentKind::Text->value, 'text' => $content]
            : $content->toBridge();

        return new Message(
            $this->session,
            $this->session->sendContent($this->id->serialized, $payload, $options?->toBridge() ?? []),
        );
    }

    public function sendSeen(): bool
    {
        return $this->session->invoke('sendSeen', [$this->id->serialized]) === true;
    }

    public function clearMessages(): bool
    {
        return $this->session->invoke('clearMessages', [$this->id->serialized]) === true;
    }

    public function delete(): bool
    {
        return $this->session->invoke('deleteChat', [$this->id->serialized]) === true;
    }

    public function archive(): void
    {
        $this->session->invoke('archiveChat', [$this->id->serialized]);
    }

    public function unarchive(): void
    {
        $this->session->invoke('unarchiveChat', [$this->id->serialized]);
    }

    public function pin(): bool
    {
        return $this->session->invoke('pinChat', [$this->id->serialized]) === true;
    }

    public function unpin(): bool
    {
        return $this->session->invoke('unpinChat', [$this->id->serialized]) === true;
    }

    public function mute(?\DateTimeInterface $unmuteDate = null): MuteResult
    {
        $result = MuteResult::fromPayload(Payload::object(
            $this->session->invoke('muteChat', [$this->id->serialized, $unmuteDate?->getTimestamp() ?? -1]),
            'Mute result',
        ));
        $this->isMuted = $result->isMuted;
        $this->muteExpiration = $result->muteExpiration;

        return $result;
    }

    public function unmute(): MuteResult
    {
        $result = MuteResult::fromPayload(Payload::object(
            $this->session->invoke('unmuteChat', [$this->id->serialized]),
            'Unmute result',
        ));
        $this->isMuted = $result->isMuted;
        $this->muteExpiration = $result->muteExpiration;

        return $result;
    }

    public function markUnread(): void
    {
        $this->session->invoke('markChatUnread', [$this->id->serialized]);
    }

    /** @return list<Message> */
    public function fetchMessages(?MessageSearchOptions $searchOptions = null): array
    {
        return array_map(
            fn (array $payload): Message => new Message($this->session, MessageData::fromPayload($payload)),
            Payload::objects(
                $this->session->invoke('fetchMessages', [$this->id->serialized, $searchOptions?->toBridge() ?? []]),
                'Messages',
            ),
        );
    }

    public function sendStateTyping(): void
    {
        $this->session->invoke('sendChatstate', ['typing', $this->id->serialized]);
    }

    public function sendStateRecording(): void
    {
        $this->session->invoke('sendChatstate', ['recording', $this->id->serialized]);
    }

    public function clearState(): bool
    {
        return $this->session->invoke('sendChatstate', ['stop', $this->id->serialized]) === true;
    }

    public function getContact(): Contact
    {
        return ContactFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getContactById', [$this->id->serialized]), 'Chat contact'),
        );
    }

    public function syncHistory(): bool
    {
        return $this->session->invoke('syncHistory', [$this->id->serialized]) === true;
    }

    /** @return list<Label> */
    public function getLabels(): array
    {
        return array_map(
            fn (array $payload): Label => new Label($this->session, $payload),
            Payload::objects($this->session->invoke('getChatLabels', [$this->id->serialized]), 'Chat labels'),
        );
    }

    /** @param list<int|string> $labelIds */
    public function changeLabels(array $labelIds): void
    {
        $this->session->invoke('addOrRemoveLabels', [$labelIds, [$this->id->serialized]]);
    }

    /** @return list<Message> */
    public function getPinnedMessages(): array
    {
        return array_map(
            fn (array $payload): Message => new Message($this->session, MessageData::fromPayload($payload)),
            Payload::objects($this->session->invoke('getPinnedMessages', [$this->id->serialized]), 'Pinned messages'),
        );
    }

    public function addOrEditCustomerNote(string $note): void
    {
        if ($this->isGroup) {
            return;
        }
        $this->session->invoke('addOrEditCustomerNote', [$this->id->serialized, $note]);
    }

    public function getCustomerNote(): ?CustomerNote
    {
        if ($this->isGroup) {
            return null;
        }
        $value = $this->session->invoke('getCustomerNote', [$this->id->serialized]);

        return $value === null ? null : CustomerNote::fromPayload(Payload::object($value, 'Customer note'));
    }
}
