<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\MessageContent;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\ChatFactory;
use Pam\WhatsApp\Support\Payload;
use Pam\WhatsApp\Support\ContactFactory;

final class Message
{
    public MessageId $id;
    public string $chatId;
    public string $from;
    public string $to;
    public string $body;
    public bool $fromMe;
    public int $timestamp;
    public MessageType $type;
    public MessageContentType $contentType;
    public MessageAck $ack;
    public ?string $mediaKey;
    public bool $hasMedia;
    public ?string $author;
    public DeviceType $deviceType;
    public bool $isForwarded;
    public int $forwardingScore;
    public bool $isStatus;
    public private(set) bool $isStarred;
    public bool $broadcast;
    public bool $hasQuotedMsg;
    public bool $hasReaction;
    public ?int $duration;
    public ?Location $location;

    /** @var list<string> */
    public array $vCards;

    /** @var list<string> */
    public array $mentionedIds;

    /** @var list<GroupMention> */
    public array $groupMentions;

    public bool $isGif;
    public bool $isEphemeral;
    public ?string $title;
    public ?string $description;
    public ?string $businessOwnerJid;
    public ?string $productId;
    public ?string $orderId;
    public ?string $token;
    public ?int $latestEditSenderTimestampMs;
    public ?MessageId $latestEditMsgKey;
    public ?MessageId $protocolMessageKey;

    /** @var list<MessageLink> */
    public array $links;

    /** @var list<array<string, mixed>> */
    public array $dynamicReplyButtons;

    public ?string $selectedButtonId;
    public ?string $selectedRowId;
    public ?string $pollName;

    /** @var list<PollOption> */
    public array $pollOptions;

    public bool $allowMultipleAnswers;

    /** @var list<int> */
    public array $messageSecret;

    public ?string $eventDescription;
    public ?int $eventStartTime;
    public ?int $eventEndTime;
    public ?EventLocation $eventLocation;
    public ?string $eventJoinLink;
    public bool $isEventCaneled;
    public ?InviteV4Data $inviteV4;

    /** @var array<string, mixed> */
    public array $rawData;

    public function __construct(private readonly Session $session, MessageData $data)
    {
        $this->applyData($data);
    }

    private function applyData(MessageData $data): void
    {
        $messageId = $data->metadata['messageId'] ?? null;
        $this->id = is_array($messageId)
            ? MessageId::fromPayload(Payload::object($messageId, 'Message id'))
            : MessageId::fromSerialized($data->id, $data->fromMe, $data->fromMe ? $data->to : $data->from);
        $this->chatId = $data->chatId;
        $this->from = $data->from;
        $this->to = $data->to;
        $this->body = $data->body;
        $this->fromMe = $data->fromMe;
        $this->timestamp = $data->timestamp;
        $this->type = $data->type;
        $this->contentType = $data->contentType;
        $metadata = $data->metadata;
        $this->rawData = $metadata;
        $this->ack = MessageAck::from(Payload::int($metadata, 'ack') ?: MessageAck::Pending->value);
        $this->mediaKey = $this->optionalString($metadata, 'mediaKey');
        $this->hasMedia = Payload::bool($metadata, 'hasMedia');
        $this->author = $this->optionalString($metadata, 'author');
        $deviceType = Payload::int($metadata, 'deviceType') ?: DeviceType::Unknown->value;
        $this->deviceType = DeviceType::from($deviceType);
        $this->isForwarded = Payload::bool($metadata, 'isForwarded');
        $this->forwardingScore = Payload::int($metadata, 'forwardingScore');
        $this->isStatus = Payload::bool($metadata, 'isStatus');
        $this->isStarred = Payload::bool($metadata, 'isStarred');
        $this->broadcast = Payload::bool($metadata, 'broadcast');
        $this->hasQuotedMsg = Payload::bool($metadata, 'hasQuotedMsg');
        $this->hasReaction = Payload::bool($metadata, 'hasReaction');
        $this->duration = $this->optionalInt($metadata, 'duration');
        $this->location = $this->location($metadata['location'] ?? null);
        $this->vCards = $this->strings($metadata['vCards'] ?? [], 'vCards');
        $this->mentionedIds = $this->strings($metadata['mentionedIds'] ?? [], 'mentionedIds');
        $this->groupMentions = array_map(
            static fn (array $mention): GroupMention => new GroupMention(
                Payload::string($mention, 'groupSubject'),
                Payload::string($mention, 'groupJid'),
            ),
            Payload::objects($metadata['groupMentions'] ?? [], 'Group mentions'),
        );
        $this->isGif = Payload::bool($metadata, 'isGif');
        $this->isEphemeral = Payload::bool($metadata, 'isEphemeral');
        $this->title = $this->optionalString($metadata, 'title');
        $this->description = $this->optionalString($metadata, 'description');
        $this->businessOwnerJid = $this->optionalString($metadata, 'businessOwnerJid');
        $this->productId = $this->optionalString($metadata, 'productId');
        $this->orderId = $this->optionalString($metadata, 'orderId');
        $this->token = $this->optionalString($metadata, 'token');
        $this->latestEditSenderTimestampMs = $this->optionalInt($metadata, 'latestEditSenderTimestampMs');
        $this->latestEditMsgKey = $this->optionalMessageId($metadata, 'latestEditMsgKey');
        $this->protocolMessageKey = $this->optionalMessageId($metadata, 'protocolMessageKey');
        $this->links = array_map(
            static fn (array $link): MessageLink => new MessageLink(
                Payload::string($link, 'link'),
                Payload::bool($link, 'isSuspicious'),
            ),
            Payload::objects($metadata['links'] ?? [], 'Message links'),
        );
        $this->dynamicReplyButtons = Payload::objects($metadata['dynamicReplyButtons'] ?? [], 'Reply buttons');
        $this->selectedButtonId = $this->optionalString($metadata, 'selectedButtonId');
        $this->selectedRowId = $this->optionalString($metadata, 'selectedRowId');
        $this->pollName = $this->optionalString($metadata, 'pollName');
        $this->pollOptions = array_map(
            static fn (array $option): PollOption => new PollOption(
                Payload::string($option, 'name'),
                Payload::int($option, 'localId'),
            ),
            Payload::objects($metadata['pollOptions'] ?? [], 'Poll options'),
        );
        $this->allowMultipleAnswers = Payload::bool($metadata, 'allowMultipleAnswers');
        $this->messageSecret = $this->integers($metadata['messageSecret'] ?? [], 'messageSecret');
        $this->eventDescription = $this->optionalString($metadata, 'eventDescription');
        $this->eventStartTime = $this->optionalInt($metadata, 'eventStartTime');
        $this->eventEndTime = $this->optionalInt($metadata, 'eventEndTime');
        $eventLocation = $metadata['eventLocation'] ?? null;
        $this->eventLocation = $eventLocation === null
            ? null
            : EventLocation::fromPayload(Payload::object($eventLocation, 'Event location'));
        $this->eventJoinLink = $this->optionalString($metadata, 'eventJoinLink');
        $this->isEventCaneled = Payload::bool($metadata, 'isEventCanceled');
        $invite = $metadata['inviteV4'] ?? null;
        $this->inviteV4 = $invite === null ? null : InviteV4Data::fromPayload(Payload::object($invite, 'Group invite'));
    }

    public function reply(
        string|MessageContent $content,
        ?string $chatId = null,
        ?MessageSendOptions $options = null,
    ): self
    {
        $payload = is_string($content)
            ? ['kind' => ContentKind::Text->value, 'text' => $content]
            : $content->toBridge();
        $sendOptions = ($options ?? new MessageSendOptions())->toBridge();
        $sendOptions['quotedMessageId'] = $this->id->serialized;

        return new self(
            $this->session,
            $this->session->sendContent($chatId ?? $this->chatId, $payload, $sendOptions),
        );
    }

    public function getChat(): Chat|Channel
    {
        return ChatFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getChatById', [$this->chatId]), 'Message chat'),
        );
    }

    public function getContact(): Contact
    {
        return ContactFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getContactById', [$this->author ?? $this->from]), 'Message contact'),
        );
    }

    /** @return list<Contact> */
    public function getMentions(): array
    {
        return array_map(
            fn (string $id): Contact => new Contact(
                $this->session,
                Payload::object($this->session->invoke('getContactById', [$id]), 'Mentioned contact'),
            ),
            $this->mentionedIds,
        );
    }

    /** @return list<GroupChat> */
    public function getGroupMentions(): array
    {
        $groups = [];
        foreach ($this->groupMentions as $mention) {
            $chat = ChatFactory::make(
                $this->session,
                Payload::object($this->session->invoke('getChatById', [$mention->groupJid]), 'Mentioned group'),
            );
            if (!$chat instanceof GroupChat) {
                throw new \UnexpectedValueException('Group mention did not resolve to a group chat.');
            }
            $groups[] = $chat;
        }

        return $groups;
    }

    public function getQuotedMessage(): ?self
    {
        if (!$this->hasQuotedMsg) {
            return null;
        }
        $value = $this->session->invoke('getQuotedMessage', [$this->id->serialized]);

        return $value === null ? null : new self(
            $this->session,
            MessageData::fromPayload(Payload::object($value, 'Quoted message')),
        );
    }

    public function forward(string|Chat $chat): void
    {
        $this->session->invoke('forwardMessage', [
            $this->id->serialized,
            is_string($chat) ? $chat : $chat->id->serialized,
        ]);
    }

    public function downloadMedia(): ?MessageMedia
    {
        if (!$this->hasMedia) {
            return null;
        }
        $value = $this->session->invoke('downloadMessageMedia', [$this->id->serialized]);
        if ($value === null) {
            return null;
        }
        $media = Payload::object($value, 'Downloaded media');

        return new MessageMedia(
            Payload::string($media, 'mimetype'),
            Payload::string($media, 'data'),
            $this->optionalString($media, 'filename'),
            $this->optionalInt($media, 'filesize'),
        );
    }

    public function downloadMediaStream(?MediaStreamOptions $options = null): ?MessageMediaStream
    {
        if (!$this->hasMedia) return null;
        $options ??= new MediaStreamOptions();
        $value = $this->session->invoke('openMessageMediaStream', [$this->id->serialized]);
        if ($value === null) return null;
        $media = Payload::object($value, 'Opened media stream');

        return new MessageMediaStream(
            new MediaStream(
                $this->session,
                Payload::string($media, 'token'),
                Payload::int($media, 'blobSize'),
                $options->chunkSize,
            ),
            Payload::string($media, 'mimetype'),
            $this->optionalString($media, 'filename'),
            $this->optionalInt($media, 'filesize'),
        );
    }

    public function delete(?bool $everyone = null, bool $clearMedia = true): void
    {
        $this->session->invoke('deleteMessage', [$this->id->serialized, $everyone, $clearMedia]);
    }

    public function star(): void
    {
        $this->session->invoke('starMessage', [$this->id->serialized, true]);
        $this->isStarred = true;
    }

    public function unstar(): void
    {
        $this->session->invoke('starMessage', [$this->id->serialized, false]);
        $this->isStarred = false;
    }

    public function pin(int $duration): bool
    {
        if ($duration <= 0) {
            throw new \InvalidArgumentException('Pin duration must be positive.');
        }

        return $this->session->invoke('pinMessage', [$this->id->serialized, true, $duration]) === true;
    }

    public function unpin(): bool
    {
        return $this->session->invoke('pinMessage', [$this->id->serialized, false, 0]) === true;
    }

    public function react(string $reaction): void
    {
        $this->session->invoke('reactMessage', [$this->id->serialized, $reaction]);
    }

    public function edit(string $content, ?MessageEditOptions $options = null): ?self
    {
        if (!$this->fromMe) {
            return null;
        }
        $value = $this->session->invoke('editMessage', [
            $this->id->serialized,
            $content,
            ($options ?? new MessageEditOptions())->toBridge(),
        ]);

        return $value === null ? null : new self(
            $this->session,
            MessageData::fromPayload(Payload::object($value, 'Edited message')),
        );
    }

    /** @param list<string> $selectedOptions */
    public function vote(array $selectedOptions): void
    {
        if ($this->contentType !== MessageContentType::Poll) {
            throw new \LogicException('Votes can only be sent for poll messages.');
        }
        $this->session->invoke('voteMessage', [$this->id->serialized, $selectedOptions]);
    }

    public function getInfo(): ?MessageInfo
    {
        $value = $this->session->invoke('getMessageInfo', [$this->id->serialized]);

        return $value === null ? null : new MessageInfo(Payload::object($value, 'Message info'));
    }

    public function getOrder(): ?Order
    {
        if ($this->contentType !== MessageContentType::Order || $this->orderId === null || $this->token === null) {
            return null;
        }
        $value = $this->session->invoke('getMessageOrder', [$this->orderId, $this->token, $this->chatId]);

        return $value === null ? null : new Order($this->session, Payload::object($value, 'Message order'));
    }

    public function getPayment(): ?Payment
    {
        if ($this->contentType !== MessageContentType::Payment) {
            return null;
        }
        $value = $this->session->invoke('getMessagePayment', [$this->id->serialized]);

        return $value === null ? null : new Payment(Payload::object($value, 'Message payment'));
    }

    /** @return list<ReactionList> */
    public function getReactions(): array
    {
        if (!$this->hasReaction) {
            return [];
        }

        return array_map(
            static fn (array $reaction): ReactionList => new ReactionList($reaction),
            Payload::objects($this->session->invoke('getMessageReactions', [$this->id->serialized]), 'Message reactions'),
        );
    }

    /** @return list<PollVote> */
    public function getPollVotes(): array
    {
        if ($this->contentType !== MessageContentType::Poll) {
            throw new \LogicException('Poll votes can only be retrieved for poll messages.');
        }

        return array_map(
            fn (array $vote): PollVote => new PollVote($this->session, $vote),
            Payload::objects($this->session->invoke('getPollVotes', [$this->id->serialized]), 'Poll votes'),
        );
    }

    public function reload(): ?self
    {
        $value = $this->session->invoke('reloadMessage', [$this->id->serialized]);
        if ($value === null) {
            return null;
        }
        $this->applyData(MessageData::fromPayload(Payload::object($value, 'Reloaded message')));

        return $this;
    }

    public function acceptGroupV4Invite(): ParticipantActionResult
    {
        if ($this->inviteV4 === null) {
            throw new \LogicException('Message does not contain a group V4 invite.');
        }
        $result = Payload::object(
            $this->session->invoke('acceptGroupV4Invite', [$this->inviteV4->toBridge()]),
            'Group invite result',
        );

        return new ParticipantActionResult(Payload::int($result, 'status'));
    }

    public function editScheduledEvent(ScheduledEvent $editedEventObject): ?self
    {
        if (!$this->fromMe) {
            return null;
        }
        $value = $this->session->invoke('editScheduledEvent', [$this->id->serialized, $editedEventObject->toBridge()]);

        return $value === null ? null : new self(
            $this->session,
            MessageData::fromPayload(Payload::object($value, 'Edited scheduled event')),
        );
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function optionalMessageId(array $payload, string $key): ?MessageId
    {
        $value = $payload[$key] ?? null;

        return $value === null
            ? null
            : MessageId::fromPayload(Payload::object($value, $key));
    }

    /** @param array<string, mixed> $payload */
    private function optionalInt(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    private function location(mixed $value): ?Location
    {
        if ($value === null) {
            return null;
        }
        $payload = Payload::object($value, 'Message location');
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        if (!is_int($latitude) && !is_float($latitude) || !is_int($longitude) && !is_float($longitude)) {
            throw new \UnexpectedValueException('Message location coordinates must be numeric.');
        }

        return new Location(
            (float) $latitude,
            (float) $longitude,
            new LocationSendOptions(
                $this->optionalString($payload, 'name'),
                $this->optionalString($payload, 'address'),
                $this->optionalString($payload, 'url'),
            ),
        );
    }

    /** @return list<string> */
    private function strings(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException($label.' must be a list.');
        }
        $values = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new \UnexpectedValueException($label.' must contain strings.');
            }
            $values[] = $item;
        }

        return $values;
    }

    /** @return list<int> */
    private function integers(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException($label.' must be a list.');
        }
        $values = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new \UnexpectedValueException($label.' must contain integers.');
            }
            $values[] = $item;
        }

        return $values;
    }
}
