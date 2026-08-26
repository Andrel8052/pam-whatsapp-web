<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\Browser\Browser;
use Pam\Browser\Page;
use Pam\WhatsApp\Bridge\BridgeEvent;
use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\MessageContent;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Event\Authenticated;
use Pam\WhatsApp\Event\ClientError;
use Pam\WhatsApp\Event\Disconnected;
use Pam\WhatsApp\Event\ChatArchiveChanged;
use Pam\WhatsApp\Event\ChatRemoved;
use Pam\WhatsApp\Event\BatteryChanged;
use Pam\WhatsApp\Event\CallReceived;
use Pam\WhatsApp\Event\ConnectionStateChanged;
use Pam\WhatsApp\Event\ContactChanged;
use Pam\WhatsApp\Event\GroupNotification;
use Pam\WhatsApp\Event\MessageAcknowledged;
use Pam\WhatsApp\Event\MessageLifecycle;
use Pam\WhatsApp\Event\LoadingScreen;
use Pam\WhatsApp\Event\RemoteSessionSaved;
use Pam\WhatsApp\Event\UnreadCountChanged;
use Pam\WhatsApp\Event\MessageReceived;
use Pam\WhatsApp\Event\QrCodeReceived;
use Pam\WhatsApp\Event\PairingCodeReceived;
use Pam\WhatsApp\Event\Ready;
use Pam\WhatsApp\Exception\BridgeException;
use Pam\WhatsApp\Exception\ContactNotFoundException;
use Pam\WhatsApp\Exception\OperationException;
use Pam\WhatsApp\Support\Payload;
use Pam\WhatsApp\Support\ChatFactory;
use Pam\WhatsApp\Support\ContactFactory;

final class Client
{
    public private(set) ClientState $state = ClientState::Created;
    public private(set) ?ClientInfo $info = null;
    public private(set) ?Browser $pupBrowser = null;
    public private(set) ?Page $pupPage = null;
    public private(set) ?InterfaceController $interface = null;

    private readonly EventDispatcher $events;

    private readonly Session $session;

    private readonly ClientOptions $options;

    private bool $reconnectPending = false;

    private int $reconnectAttempts = 0;

    /** @var array<int, list<callable(MessageAcknowledged): void>> */
    private array $deliveryListeners = [];

    public function __construct(?ClientOptions $options = null)
    {
        $options ??= new ClientOptions();
        $this->options = $options;
        $this->session = $options->session ?? BrowserSession::create($options);
        $this->events = new EventDispatcher();
        if ($this->session instanceof BrowserSession) {
            $this->session->setupAuthStrategy($this);
            $this->pupBrowser = $this->session->currentBrowser();
        }
    }

    public static function forSession(Session $session): self
    {
        return new self(new ClientOptions(session: $session));
    }

    public static function launch(?ClientOptions $options = null): self
    {
        $client = new self($options);
        if (!($client->session instanceof BrowserSession)) {
            throw new \LogicException('Client launch requires a browser session.');
        }
        $client->session->launchBrowser();
        $client->pupBrowser = $client->session->browser();

        return $client;
    }

    /** @param callable(QrCodeReceived): void $listener */
    public function onQrCode(callable $listener): self
    {
        $this->events->on(EventType::QrCode, $listener);

        return $this;
    }

    /** @param callable(Ready): void $listener */
    public function onReady(callable $listener): self
    {
        $this->events->on(EventType::Ready, $listener);

        return $this;
    }

    /** @param callable(MessageReceived): void $listener */
    public function onMessage(callable $listener): self
    {
        $this->events->on(EventType::MessageReceived, $listener);

        return $this;
    }

    /** @param callable(MessageAcknowledged): void $listener */
    public function onMessageSent(callable $listener): self
    {
        $this->deliveryListeners[DeliveryEventType::Sent->value][] = $listener;

        return $this;
    }

    /** @param callable(MessageAcknowledged): void $listener */
    public function onMessageDelivered(callable $listener): self
    {
        $this->deliveryListeners[DeliveryEventType::Delivered->value][] = $listener;

        return $this;
    }

    /** @param callable(MessageAcknowledged): void $listener */
    public function onMessageRead(callable $listener): self
    {
        $this->deliveryListeners[DeliveryEventType::Read->value][] = $listener;

        return $this;
    }

    /** @param callable(MessageAcknowledged): void $listener */
    public function onMessageFailed(callable $listener): self
    {
        $this->deliveryListeners[DeliveryEventType::Failed->value][] = $listener;

        return $this;
    }

    /** @param callable(object): void $listener */
    public function on(EventType $event, callable $listener): self
    {
        $this->events->on($event, $listener);

        return $this;
    }

    public function initialize(): void
    {
        if ($this->state !== ClientState::Created) {
            throw new \LogicException('WhatsApp client can only be initialized once.');
        }

        $this->state = ClientState::Initializing;
        try {
            $this->state = ClientState::AwaitingAuthentication;
            $this->session->initialize($this->handleBridgeEvent(...));
            if ($this->session instanceof BrowserSession) {
                $this->pupBrowser = $this->session->browser();
                $this->pupPage = $this->session->currentPage();
            }
            $this->log(LogLevel::Info, 'WhatsApp client initialized.', []);
        } catch (\Throwable $exception) {
            $this->state = ClientState::Failed;
            $this->log(LogLevel::Error, 'WhatsApp client initialization failed.', [
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    public function diagnoseSession(): SessionDiagnostics
    {
        $errors = [];
        $connectionState = null;
        $webVersion = null;
        if ($this->state === ClientState::Ready) {
            try {
                $connectionState = $this->getState();
            } catch (\Throwable $exception) {
                $errors[] = 'Connection state: '.$exception->getMessage();
            }
            try {
                $webVersion = $this->getWWebVersion();
            } catch (\Throwable $exception) {
                $errors[] = 'Web version: '.$exception->getMessage();
            }
        }

        return new SessionDiagnostics(
            $this->state,
            $this->session instanceof BrowserSession ? $this->pupBrowser !== null : true,
            $connectionState,
            $webVersion,
            $this->info?->wid->serialized,
            $errors,
        );
    }

    public function sendMessage(
        string $chatId,
        string|MessageContent $content,
        ?MessageSendOptions $options = null,
    ): Message
    {
        if ($this->state !== ClientState::Ready) {
            throw new \LogicException('WhatsApp client is not ready.');
        }

        $payload = is_string($content)
            ? ['kind' => ContentKind::Text->value, 'text' => $content]
            : $content->toBridge();

        try {
            return new Message(
                $this->session,
                $this->session->sendContent($chatId, $payload, $options?->toBridge() ?? []),
            );
        } catch (\Throwable $exception) {
            throw new OperationException('sendMessage', $chatId, 1, $exception);
        }
    }

    public function sendMessageToNumber(
        string $phoneNumber,
        string|MessageContent $content,
        ?MessageSendOptions $options = null,
        ?RetryOptions $retry = null,
    ): Message {
        $phone = new PhoneNumber($phoneNumber);
        $retry ??= new RetryOptions();
        $contact = $this->executeWithRetry(
            'getNumberId',
            $phone->digits,
            $retry,
            fn (): ?ContactId => $this->getNumberId($phone->digits),
        );
        if (!$contact instanceof ContactId) {
            throw new ContactNotFoundException($phone->digits);
        }

        return $this->executeWithRetry(
            'sendMessageToNumber',
            $contact->serialized,
            $retry,
            fn (): Message => $this->sendMessage($contact->serialized, $content, $options),
        );
    }

    public function sendImageToNumber(string $phoneNumber, string $filePath, ?string $caption = null): Message
    {
        return $this->sendMessageToNumber(
            $phoneNumber,
            MessageMedia::fromFilePath($filePath),
            new MessageSendOptions(caption: $caption),
        );
    }

    public function sendAudioToNumber(string $phoneNumber, string $filePath, bool $asVoiceNote = true): Message
    {
        return $this->sendMessageToNumber(
            $phoneNumber,
            MessageMedia::fromFilePath($filePath),
            new MessageSendOptions(sendAudioAsVoice: $asVoiceNote),
        );
    }

    public function sendDocumentToNumber(string $phoneNumber, string $filePath, ?string $caption = null): Message
    {
        return $this->sendMessageToNumber(
            $phoneNumber,
            MessageMedia::fromFilePath($filePath),
            new MessageSendOptions(sendMediaAsDocument: true, caption: $caption),
        );
    }

    public function sendStickerToNumber(
        string $phoneNumber,
        string $filePath,
        ?string $name = null,
        ?string $author = null,
    ): Message {
        return $this->sendMessageToNumber(
            $phoneNumber,
            MessageMedia::fromFilePath($filePath),
            new MessageSendOptions(sendMediaAsSticker: true, stickerName: $name, stickerAuthor: $author),
        );
    }

    /** @return list<Chat> */
    public function getChats(): array
    {
        return array_map(
            function (array $payload): Chat {
                $chat = ChatFactory::make($this->session, $payload);
                if ($chat instanceof Channel) {
                    throw new \UnexpectedValueException('The chats collection cannot contain a channel.');
                }

                return $chat;
            },
            Payload::objects($this->session->invoke('getChats'), 'Chats'),
        );
    }

    /** @return list<Channel> */
    public function getChannels(): array
    {
        return array_map(
            fn (array $payload): Channel => new Channel($this->session, $payload),
            Payload::objects($this->session->invoke('getChannels'), 'Channels'),
        );
    }

    public function getChatById(string $chatId): Chat|Channel
    {
        return ChatFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getChatById', [$chatId]), 'Chat'),
        );
    }

    /** @return array<string, mixed> */
    public function getInviteInfo(string $inviteCode): array
    {
        return Payload::object($this->session->invoke('getInviteInfo', [$inviteCode]), 'Invite info');
    }

    public function acceptInvite(string $inviteCode): string
    {
        $value = $this->session->invoke('acceptInvite', [$inviteCode]);

        return Payload::string(['id' => $value], 'id');
    }

    public function requestPairingCode(
        string $phoneNumber,
        bool $showNotification = true,
        int $intervalMs = 180_000,
    ): string {
        $value = $this->session->invoke('requestPairingCode', [$phoneNumber, $showNotification, $intervalMs]);

        return Payload::string(['code' => $value], 'code');
    }

    public function cancelPairingCode(): void
    {
        $this->session->invoke('cancelPairingCode');
    }

    public function resetState(): void
    {
        $this->session->invoke('resetState');
    }

    public function createCallLink(\DateTimeInterface $startTime, ScheduledEventCallType $callType): string
    {
        $value = $this->session->invoke('createCallLink', [$startTime->getTimestamp(), $callType->value]);

        return Payload::string(['link' => $value], 'link');
    }

    public function sendResponseToScheduledEvent(
        ScheduledEventResponse $response,
        string|Message $eventMessageId,
    ): bool {
        return $this->session->invoke('sendResponseToScheduledEvent', [
            $response->value,
            is_string($eventMessageId) ? $eventMessageId : $eventMessageId->id->serialized,
        ]) === true;
    }

    public function sendReaction(string $messageId, string $reaction): void
    {
        $this->session->invoke('sendReaction', [$messageId, $reaction]);
    }

    /** @return list<Message> */
    public function searchMessages(string $query, ?MessageSearchQueryOptions $options = null): array
    {
        return array_map(
            fn (array $payload): Message => new Message($this->session, MessageData::fromPayload($payload)),
            Payload::objects($this->session->invoke('searchMessages', [$query, ($options ?? new MessageSearchQueryOptions())->toBridge()]), 'Messages'),
        );
    }

    public function getMessageById(string $messageId): ?Message
    {
        $payload = $this->session->invoke('getMessageById', [$messageId]);

        return $payload === null ? null : new Message($this->session, MessageData::fromPayload(Payload::object($payload, 'Message')));
    }

    public function getLabelById(string $labelId): Label
    {
        return new Label($this->session, Payload::object($this->session->invoke('getLabelById', [$labelId]), 'Label'));
    }

    /** @return list<Chat> */
    public function getChatsByLabelId(string $labelId): array
    {
        return array_map(
            function (array $payload): Chat {
                $chat = ChatFactory::make($this->session, $payload);
                if ($chat instanceof Channel) {
                    throw new \UnexpectedValueException('A label chat collection cannot contain a channel.');
                }

                return $chat;
            },
            Payload::objects($this->session->invoke('getChatsByLabelId', [$labelId]), 'Chats'),
        );
    }

    public function setProfilePicture(MessageMedia $media): bool
    {
        $wid = $this->info?->wid->serialized ?? throw new \LogicException('Client info is unavailable before ready.');

        return $this->session->invoke('setProfilePicture', [$wid, $media->toBridge()['media']]) === true;
    }

    public function deleteProfilePicture(): bool
    {
        $wid = $this->info?->wid->serialized ?? throw new \LogicException('Client info is unavailable before ready.');

        return $this->session->invoke('deleteProfilePicture', [$wid]) === true;
    }

    public function revokeStatusMessage(string $messageId): void
    {
        $this->session->invoke('revokeStatusMessage', [$messageId]);
    }

    public function setAutoDownloadAudio(bool $flag): void
    {
        $this->session->invoke('setAutoDownload', ['audio', $flag]);
    }

    public function setAutoDownloadDocuments(bool $flag): void
    {
        $this->session->invoke('setAutoDownload', ['documents', $flag]);
    }

    public function setAutoDownloadPhotos(bool $flag): void
    {
        $this->session->invoke('setAutoDownload', ['photos', $flag]);
    }

    public function setAutoDownloadVideos(bool $flag): void
    {
        $this->session->invoke('setAutoDownload', ['videos', $flag]);
    }

    public function setBackgroundSync(bool $flag): void
    {
        $this->session->invoke('setBackgroundSync', [$flag]);
    }

    public function getContactDeviceCount(string $userId): int
    {
        $value = $this->session->invoke('getContactDeviceCount', [$userId]);

        return is_int($value) ? $value : 0;
    }

    public function saveOrEditAddressbookContact(
        string $phoneNumber,
        string $firstName,
        string $lastName,
        bool $syncToAddressbook = false,
    ): void {
        $this->session->invoke('saveOrEditAddressbookContact', [$phoneNumber, $firstName, $lastName, $syncToAddressbook]);
    }

    public function deleteAddressbookContact(string $phoneNumber): void
    {
        $this->session->invoke('deleteAddressbookContact', [$phoneNumber]);
    }

    /**
     * @param list<string> $userIds
     * @return list<ContactLidAndPhone>
     */
    public function getContactLidAndPhone(array $userIds): array
    {
        return array_map(
            static fn (array $payload): ContactLidAndPhone => new ContactLidAndPhone($payload),
            Payload::objects($this->session->invoke('getContactLidAndPhone', [$userIds]), 'Contact ids'),
        );
    }

    public function getChannelByInviteCode(string $inviteCode): ?Channel
    {
        $payload = $this->session->invoke('getChannelByInviteCode', [$inviteCode]);

        return $payload === null ? null : new Channel($this->session, Payload::object($payload, 'Channel'));
    }

    public function createChannel(string $title, ?CreateChannelOptions $options = null): CreateChannelResult|string
    {
        $result = $this->session->invoke('createChannel', [$title, $options?->toBridge() ?? []]);
        if (is_string($result)) {
            return $result;
        }

        return new CreateChannelResult(Payload::object($result, 'Create channel result'));
    }

    /** @param string|Contact|list<string|Contact> $participants */
    public function createGroup(
        string $title,
        string|Contact|array $participants = [],
        ?CreateGroupOptions $options = null,
    ): CreateGroupResult|string {
        $items = is_array($participants) ? $participants : [$participants];
        $participantIds = [];
        foreach ($items as $participant) {
            $participantIds[] = is_string($participant) ? $participant : $participant->id->serialized;
        }
        $result = $this->session->invoke('createGroup', [$title, $participantIds, $options?->toBridge() ?? []]);
        if (is_string($result)) {
            return $result;
        }

        return new CreateGroupResult(Payload::object($result, 'Create group result'));
    }

    public function subscribeToChannel(string $channelId): bool
    {
        return $this->session->invoke('subscribeToChannel', [$channelId]) === true;
    }

    public function unsubscribeFromChannel(string $channelId, ?UnsubscribeOptions $options = null): bool
    {
        return $this->session->invoke('unsubscribeFromChannel', [$channelId, $options?->toBridge() ?? []]) === true;
    }

    public function transferChannelOwnership(
        string $channelId,
        string $newOwnerId,
        ?TransferChannelOwnershipOptions $options = null,
    ): bool {
        return $this->session->invoke('transferChannelOwnership', [$channelId, $newOwnerId, $options?->toBridge() ?? []]) === true;
    }

    /** @return list<Channel> */
    public function searchChannels(?SearchChannelsOptions $searchOptions = null): array
    {
        return array_map(
            fn (array $payload): Channel => new Channel($this->session, $payload),
            Payload::objects($this->session->invoke('searchChannels', [($searchOptions ?? new SearchChannelsOptions())->toBridge()]), 'Channels'),
        );
    }

    public function deleteChannel(string $channelId): bool
    {
        return $this->session->invoke('deleteChannel', [$channelId]) === true;
    }

    public function sendChannelAdminInvite(
        string $chatId,
        string $channelId,
        ?SendChannelAdminInviteOptions $options = null,
    ): bool {
        return $this->session->invoke('sendChannelAdminInvite', [$chatId, $channelId, $options?->toBridge() ?? []]) === true;
    }

    public function acceptChannelAdminInvite(string $channelId): bool
    {
        return $this->session->invoke('acceptChannelAdminInvite', [$channelId]) === true;
    }

    public function revokeChannelAdminInvite(string $channelId, string $userId): bool
    {
        return $this->session->invoke('revokeChannelAdminInvite', [$channelId, $userId]) === true;
    }

    public function demoteChannelAdmin(string $channelId, string $userId): bool
    {
        return $this->session->invoke('demoteChannelAdmin', [$channelId, $userId]) === true;
    }

    /** @return list<Contact> */
    public function getContacts(): array
    {
        return array_map(
            fn (array $payload): Contact => ContactFactory::make($this->session, $payload),
            Payload::objects($this->session->invoke('getContacts'), 'Contacts'),
        );
    }

    public function getContactById(string $contactId): Contact
    {
        return ContactFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getContactById', [$contactId]), 'Contact'),
        );
    }

    /** @return list<Contact> */
    public function getBlockedContacts(): array
    {
        return array_map(
            fn (array $payload): Contact => ContactFactory::make($this->session, $payload),
            Payload::objects($this->session->invoke('getBlockedContacts'), 'Blocked contacts'),
        );
    }

    /** @return list<ContactId> */
    public function getCommonGroups(string $contactId): array
    {
        return array_map(
            static fn (array $group): ContactId => ContactId::fromPayload($group),
            Payload::objects($this->session->invoke('getCommonGroups', [$contactId]), 'Common groups'),
        );
    }

    /** @return list<Broadcast> */
    public function getBroadcasts(): array
    {
        return array_map(
            fn (array $broadcast): Broadcast => new Broadcast($this->session, $broadcast),
            Payload::objects($this->session->invoke('getBroadcasts'), 'Broadcasts'),
        );
    }

    public function getBroadcastById(string $contactId): ?Broadcast
    {
        $value = $this->session->invoke('getBroadcastById', [$contactId]);

        return $value === null ? null : new Broadcast($this->session, Payload::object($value, 'Broadcast'));
    }

    public function getWWebVersion(): string
    {
        $version = $this->session->invoke('getWWebVersion');
        if (!is_string($version) || $version === '') {
            throw new BridgeException('WhatsApp Web did not return a version.');
        }

        return $version;
    }

    public function getState(): ConnectionState
    {
        $state = $this->session->invoke('getState');
        if (!is_int($state)) {
            throw new BridgeException('WhatsApp Web did not return an integer connection state.');
        }

        return ConnectionState::from($state);
    }

    public function sendSeen(string $chatId): bool
    {
        return $this->session->invoke('sendSeen', [$chatId]) === true;
    }

    public function archiveChat(string $chatId): bool
    {
        return $this->session->invoke('archiveChat', [$chatId]) === true;
    }

    public function unarchiveChat(string $chatId): bool
    {
        return $this->session->invoke('unarchiveChat', [$chatId]) === true;
    }

    public function pinChat(string $chatId): bool
    {
        return $this->session->invoke('pinChat', [$chatId]) === true;
    }

    public function unpinChat(string $chatId): bool
    {
        return $this->session->invoke('unpinChat', [$chatId]) === true;
    }

    public function muteChat(string $chatId, ?\DateTimeInterface $unmuteDate = null): MuteResult
    {
        $timestamp = $unmuteDate?->getTimestamp() ?? -1;

        return MuteResult::fromPayload(Payload::object(
            $this->session->invoke('muteChat', [$chatId, $timestamp]),
            'Mute result',
        ));
    }

    public function unmuteChat(string $chatId): MuteResult
    {
        return MuteResult::fromPayload(Payload::object(
            $this->session->invoke('unmuteChat', [$chatId]),
            'Unmute result',
        ));
    }

    public function markChatUnread(string $chatId): void
    {
        $this->session->invoke('markChatUnread', [$chatId]);
    }

    public function getProfilePicUrl(string $contactId): ?string
    {
        $value = $this->session->invoke('getProfilePicUrl', [$contactId]);
        if ($value !== null && !is_string($value)) {
            throw new BridgeException('Profile picture URL must be a string or null.');
        }

        return $value;
    }

    public function getNumberId(string $number): ?ContactId
    {
        $normalized = str_ends_with($number, '@c.us')
            ? substr($number, 0, -5)
            : (new PhoneNumber($number))->digits;
        $value = $this->session->invoke('getNumberId', [$normalized]);
        if ($value === null) {
            return null;
        }

        return ContactId::fromPayload(Payload::object($value, 'Contact id'));
    }

    public function isRegisteredUser(string $contactId): bool
    {
        return $this->getNumberId($contactId) !== null;
    }

    public function getFormattedNumber(string $number): string
    {
        return $this->stringResult('getFormattedNumber', [$number], 'Formatted number');
    }

    public function getCountryCode(string $number): string
    {
        return $this->stringResult('getCountryCode', [$number], 'Country code');
    }

    public function sendPresenceAvailable(): void
    {
        $this->session->invoke('sendPresenceAvailable');
    }

    public function sendPresenceUnavailable(): void
    {
        $this->session->invoke('sendPresenceUnavailable');
    }

    public function setStatus(string $status): void
    {
        $this->session->invoke('setStatus', [$status]);
    }

    public function setDisplayName(string $displayName): bool
    {
        return $this->session->invoke('setDisplayName', [$displayName]) === true;
    }

    public function syncHistory(string $chatId): bool
    {
        return $this->session->invoke('syncHistory', [$chatId]) === true;
    }

    /** @return list<Label> */
    public function getLabels(): array
    {
        return $this->labels($this->session->invoke('getLabels'), 'Labels');
    }

    /** @return list<Label> */
    public function getChatLabels(string $chatId): array
    {
        return $this->labels($this->session->invoke('getChatLabels', [$chatId]), 'Chat labels');
    }

    /** @return list<Message> */
    public function getPinnedMessages(string $chatId): array
    {
        return array_map(
            fn (array $payload): Message => new Message($this->session, MessageData::fromPayload($payload)),
            Payload::objects($this->session->invoke('getPinnedMessages', [$chatId]), 'Pinned messages'),
        );
    }

    /**
     * @param list<int|string> $labelIds
     * @param list<string> $chatIds
     */
    public function addOrRemoveLabels(array $labelIds, array $chatIds): void
    {
        $this->session->invoke('addOrRemoveLabels', [$labelIds, $chatIds]);
    }

    public function addOrEditCustomerNote(string $userId, string $note): void
    {
        $this->session->invoke('addOrEditCustomerNote', [$userId, $note]);
    }

    public function getCustomerNote(string $userId): ?CustomerNote
    {
        $value = $this->session->invoke('getCustomerNote', [$userId]);

        return $value === null ? null : CustomerNote::fromPayload(Payload::object($value, 'Customer note'));
    }

    /** @return list<GroupMembershipRequest> */
    public function getGroupMembershipRequests(string $groupId): array
    {
        return array_map(
            static fn (array $payload): GroupMembershipRequest => new GroupMembershipRequest($payload),
            Payload::objects($this->session->invoke('getGroupMembershipRequests', [$groupId]), 'Group membership requests'),
        );
    }

    /** @return list<MembershipRequestActionResult> */
    public function approveGroupMembershipRequests(
        string $groupId,
        ?MembershipRequestActionOptions $options = null,
    ): array {
        return $this->membershipRequestAction($groupId, MembershipRequestAction::Approve, $options);
    }

    /** @return list<MembershipRequestActionResult> */
    public function rejectGroupMembershipRequests(
        string $groupId,
        ?MembershipRequestActionOptions $options = null,
    ): array {
        return $this->membershipRequestAction($groupId, MembershipRequestAction::Reject, $options);
    }

    public function acceptGroupV4Invite(InviteV4Data $inviteV4): ParticipantActionResult
    {
        $result = Payload::object(
            $this->session->invoke('acceptGroupV4Invite', [$inviteV4->toBridge()]),
            'Group invite result',
        );

        return new ParticipantActionResult(Payload::int($result, 'status'));
    }

    /** @return list<PollVote> */
    public function getPollVotes(string $messageId): array
    {
        return array_map(
            fn (array $vote): PollVote => new PollVote($this->session, $vote),
            Payload::objects($this->session->invoke('getPollVotes', [$messageId]), 'Poll votes'),
        );
    }

    public function pump(float $timeoutSeconds = 1.0): bool
    {
        if ($this->reconnectPending) {
            return $this->performReconnect();
        }
        $handled = $this->session->pump($timeoutSeconds);
        if ($this->session instanceof BrowserSession) {
            $this->pupBrowser = $this->session->browser();
            $this->pupPage = $this->session->currentPage();
        }

        return $handled;
    }

    public function run(): void
    {
        while (!in_array($this->state, [ClientState::Closed, ClientState::Failed], true)) {
            $this->pump(30.0);
        }
    }

    public function reconnect(): void
    {
        if (!$this->session instanceof BrowserSession) {
            throw new \LogicException('Reconnect requires a browser session.');
        }
        if (!in_array($this->state, [ClientState::Closed, ClientState::Failed], true)) {
            throw new \LogicException('Reconnect is only available after the client disconnects or fails.');
        }

        $this->reconnectAttempts = 0;
        $this->reconnectPending = true;
        $this->state = ClientState::Initializing;
    }

    public function close(): void
    {
        $this->destroy();
    }

    public function destroy(): void
    {
        if ($this->state === ClientState::Closed) {
            return;
        }
        $this->session->close();
        $this->state = ClientState::Closed;
    }

    public function logout(): void
    {
        if ($this->state === ClientState::Closed) {
            return;
        }
        $this->session->logout();
        $this->state = ClientState::Closed;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function executeWithRetry(
        string $name,
        ?string $target,
        RetryOptions $options,
        callable $operation,
    ): mixed {
        $delayMs = $options->initialDelayMs;
        for ($attempt = 1; $attempt <= $options->maxAttempts; $attempt++) {
            try {
                return $operation();
            } catch (ContactNotFoundException|\InvalidArgumentException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $wrapped = $exception instanceof OperationException
                    ? new OperationException($name, $target, $attempt, $exception->getPrevious() ?? $exception)
                    : new OperationException($name, $target, $attempt, $exception);
                $this->log(LogLevel::Warning, $wrapped->getMessage(), [
                    'operation' => $name,
                    'target' => $target,
                    'attempt' => $attempt,
                ]);
                if ($attempt === $options->maxAttempts) {
                    throw $wrapped;
                }
                if ($delayMs > 0) {
                    usleep($delayMs * 1_000);
                }
                $delayMs = min($options->maximumDelayMs, (int) ceil($delayMs * $options->multiplier));
            }
        }

        throw new \LogicException('Retry loop ended unexpectedly.');
    }

    private function performReconnect(): bool
    {
        if (!$this->session instanceof BrowserSession) {
            throw new \LogicException('Reconnect requires a browser session.');
        }
        $this->reconnectPending = false;
        $this->reconnectAttempts++;
        if ($this->options->reconnectDelayMs > 0) {
            usleep($this->options->reconnectDelayMs * 1_000);
        }

        try {
            $this->session->reconnect($this->handleBridgeEvent(...));
            $this->reconnectAttempts = 0;
            $this->log(LogLevel::Info, 'WhatsApp reconnected.', []);

            return true;
        } catch (\Throwable $exception) {
            if ($this->reconnectAttempts < $this->options->reconnectMaxAttempts) {
                $this->reconnectPending = true;
                $this->log(LogLevel::Warning, 'WhatsApp reconnect attempt failed.', [
                    'attempt' => $this->reconnectAttempts,
                    'error' => $exception->getMessage(),
                ]);

                return false;
            }
            $this->state = ClientState::Failed;
            throw new OperationException('reconnect', null, $this->reconnectAttempts, $exception);
        }
    }

    /** @param array<string, mixed> $context */
    private function log(LogLevel $level, string $message, array $context): void
    {
        if ($this->options->logger !== null) {
            try {
                ($this->options->logger)($level, $message, $context);
            } catch (\Throwable) {
                // Application logging must never break the WhatsApp lifecycle.
            }
        }
    }

    private function handleBridgeEvent(BridgeEvent $event): void
    {
        try {
            match ($event->type) {
                EventType::QrCode => $this->qrCode($event),
                EventType::Authenticated => $this->authenticated($event),
                EventType::Ready => $this->ready($event),
                EventType::MessageReceived => $this->message($event),
                EventType::Disconnected => $this->disconnected($event),
                EventType::Error => $this->error($event),
                EventType::AuthenticationFailure => $this->authenticationFailure($event),
                EventType::PairingCodeReceived => $this->pairingCode($event),
                EventType::ContactChanged => $this->contactChanged($event),
                EventType::MessageReaction => $this->reactionUpdated($event),
                EventType::VoteUpdated => $this->voteUpdated($event),
                EventType::MessageAcknowledged => $this->messageAcknowledged($event),
                EventType::StateChanged => $this->stateChanged($event),
                EventType::ChatArchived => $this->chatArchived($event),
                EventType::ChatRemoved => $this->chatRemoved($event),
                EventType::UnreadCountChanged => $this->unreadCountChanged($event),
                EventType::BatteryChanged => $this->batteryChanged($event),
                EventType::CallReceived => $this->callReceived($event),
                EventType::LoadingScreen => $this->loadingScreen($event),
                EventType::RemoteSessionSaved => $this->remoteSessionSaved($event),
                EventType::GroupAdminChanged,
                EventType::GroupJoined,
                EventType::GroupLeft,
                EventType::GroupMembershipRequest,
                EventType::GroupUpdated => $this->groupNotification($event),
                EventType::MessageCreated,
                EventType::MessageCiphertext,
                EventType::MessageCiphertextFailed,
                EventType::MessageEdited,
                EventType::MessageRevokedEveryone,
                EventType::MessageRevokedMe,
                EventType::MediaUploaded => $this->messageLifecycle($event),
            };
        } catch (\Throwable $exception) {
            $this->state = ClientState::Failed;
            throw new BridgeException('Unable to handle a WhatsApp bridge event.', previous: $exception);
        }
    }

    private function qrCode(BridgeEvent $event): void
    {
        $code = $event->payload['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new BridgeException('QR event must contain a non-empty code.');
        }
        $this->state = ClientState::AwaitingAuthentication;
        $this->events->dispatch(EventType::QrCode, new QrCodeReceived($code));
    }

    private function authenticated(BridgeEvent $event): void
    {
        $timestamp = $this->timestamp($event);
        $this->events->dispatch(
            EventType::Authenticated,
            new Authenticated($timestamp, $event->payload['authPayload'] ?? null),
        );
    }

    private function authenticationFailure(BridgeEvent $event): void
    {
        $this->state = ClientState::Failed;
        $this->events->dispatch($event->type, new ClientError(Payload::string($event->payload, 'message')));
    }

    private function pairingCode(BridgeEvent $event): void
    {
        $this->events->dispatch($event->type, new PairingCodeReceived(Payload::string($event->payload, 'code')));
    }

    private function contactChanged(BridgeEvent $event): void
    {
        $this->events->dispatch($event->type, new ContactChanged(
            $this->messageFromNestedPayload($event),
            Payload::string($event->payload, 'oldId'),
            Payload::string($event->payload, 'newId'),
            Payload::bool($event->payload, 'isContact'),
        ));
    }

    private function reactionUpdated(BridgeEvent $event): void
    {
        $this->events->dispatch(
            $event->type,
            new Reaction(Payload::object($event->payload['reaction'] ?? null, 'Reaction')),
        );
    }

    private function voteUpdated(BridgeEvent $event): void
    {
        $this->events->dispatch(
            $event->type,
            new PollVote($this->session, Payload::object($event->payload['vote'] ?? null, 'Poll vote')),
        );
    }

    private function ready(BridgeEvent $event): void
    {
        $timestamp = $this->timestamp($event);
        $info = $event->payload['info'] ?? null;
        if (is_array($info)) {
            $this->info = new ClientInfo($this->session, Payload::object($info, 'Client info'));
        }
        $this->interface = new InterfaceController($this->session);
        $this->state = ClientState::Ready;
        $this->events->dispatch(EventType::Ready, new Ready($timestamp));
    }

    private function message(BridgeEvent $event): void
    {
        $message = new Message($this->session, MessageData::fromPayload($event->payload));
        $this->events->dispatch(EventType::MessageReceived, new MessageReceived($message));
    }

    private function disconnected(BridgeEvent $event): void
    {
        $reason = $event->payload['reason'] ?? null;
        if (!is_int($reason)) {
            throw new BridgeException('Disconnected event must contain an integer reason.');
        }
        $disconnectionReason = DisconnectionReason::from($reason);
        if ($this->session instanceof BrowserSession) {
            $this->session->disconnect($disconnectionReason);
        }
        $this->state = ClientState::Closed;
        $this->events->dispatch(EventType::Disconnected, new Disconnected($disconnectionReason));
        if ($this->reconnectPending) {
            return;
        }
        if ($this->options->autoReconnect
            && in_array($disconnectionReason, [DisconnectionReason::Conflict, DisconnectionReason::Unlaunched], true)
            && $this->session instanceof BrowserSession
        ) {
            $this->reconnectPending = true;
            $this->state = ClientState::Initializing;
            $this->log(LogLevel::Warning, 'WhatsApp disconnected; reconnect scheduled.', [
                'reason' => $disconnectionReason->value,
            ]);

            return;
        }
    }

    private function error(BridgeEvent $event): void
    {
        $message = $event->payload['message'] ?? null;
        if (!is_string($message)) {
            throw new BridgeException('Error event must contain a message.');
        }
        $this->state = ClientState::Failed;
        $this->events->dispatch(EventType::Error, new ClientError($message));
    }

    private function messageAcknowledged(BridgeEvent $event): void
    {
        $message = $this->messageFromNestedPayload($event);
        $ack = $event->payload['ack'] ?? null;
        if (!is_int($ack)) {
            throw new BridgeException('Message acknowledgement must contain an integer ack.');
        }
        $acknowledged = new MessageAcknowledged($message, MessageAck::from($ack));
        $this->events->dispatch($event->type, $acknowledged);
        $derived = match ($acknowledged->ack) {
            MessageAck::Error => DeliveryEventType::Failed,
            MessageAck::Server => DeliveryEventType::Sent,
            MessageAck::Device => DeliveryEventType::Delivered,
            MessageAck::Read, MessageAck::Played => DeliveryEventType::Read,
            MessageAck::Pending => null,
        };
        if ($derived !== null) {
            foreach ($this->deliveryListeners[$derived->value] ?? [] as $listener) {
                $listener($acknowledged);
            }
        }
    }

    private function messageLifecycle(BridgeEvent $event): void
    {
        $message = $this->messageFromNestedPayload($event);
        $context = $event->payload;
        unset($context['message']);
        $this->events->dispatch($event->type, new MessageLifecycle($event->type, $message, $context));
    }

    private function stateChanged(BridgeEvent $event): void
    {
        $state = $event->payload['state'] ?? null;
        if (!is_int($state)) {
            throw new BridgeException('State change must contain an integer state.');
        }
        $this->events->dispatch($event->type, new ConnectionStateChanged(ConnectionState::from($state)));
    }

    private function chatArchived(BridgeEvent $event): void
    {
        $this->events->dispatch($event->type, new ChatArchiveChanged(
            Payload::string($event->payload, 'chatId'),
            Payload::bool($event->payload, 'archived'),
            Payload::bool($event->payload, 'previousArchived'),
        ));
    }

    private function chatRemoved(BridgeEvent $event): void
    {
        $this->events->dispatch(
            $event->type,
            new ChatRemoved(Payload::string($event->payload, 'chatId')),
        );
    }

    private function unreadCountChanged(BridgeEvent $event): void
    {
        $this->events->dispatch($event->type, new UnreadCountChanged(
            Payload::string($event->payload, 'chatId'),
            Payload::int($event->payload, 'unreadCount'),
        ));
    }

    private function groupNotification(BridgeEvent $event): void
    {
        $recipients = $event->payload['recipientIds'] ?? [];
        if (!is_array($recipients)) {
            throw new BridgeException('Group notification recipient ids must be strings.');
        }
        $recipientIds = [];
        foreach ($recipients as $recipient) {
            if (!is_string($recipient)) {
                throw new BridgeException('Group notification recipient ids must be strings.');
            }
            $recipientIds[] = $recipient;
        }
        $this->events->dispatch($event->type, new GroupNotification(
            $this->session,
            $event->type,
            GroupNotificationType::tryFrom(Payload::int($event->payload, 'type')) ?? GroupNotificationType::Add,
            Payload::string($event->payload, 'id'),
            Payload::string($event->payload, 'author'),
            Payload::string($event->payload, 'body'),
            Payload::string($event->payload, 'chatId'),
            $recipientIds,
            Payload::int($event->payload, 'timestamp'),
        ));
    }

    private function batteryChanged(BridgeEvent $event): void
    {
        $this->events->dispatch($event->type, new BatteryChanged(
            Payload::int($event->payload, 'battery'),
            Payload::bool($event->payload, 'plugged'),
        ));
    }

    private function callReceived(BridgeEvent $event): void
    {
        $this->events->dispatch($event->type, new CallReceived(new Call($this->session, $event->payload)));
    }

    private function loadingScreen(BridgeEvent $event): void
    {
        $this->events->dispatch($event->type, new LoadingScreen(
            Payload::int($event->payload, 'percent'),
            Payload::string($event->payload, 'message'),
        ));
    }

    private function remoteSessionSaved(BridgeEvent $event): void
    {
        $this->events->dispatch(
            $event->type,
            new RemoteSessionSaved($this->timestamp($event)),
        );
    }

    private function messageFromNestedPayload(BridgeEvent $event): Message
    {
        return new Message(
            $this->session,
            MessageData::fromPayload(Payload::object($event->payload['message'] ?? null, 'Event message')),
        );
    }

    private function timestamp(BridgeEvent $event): int
    {
        $timestamp = $event->payload['timestamp'] ?? null;
        if (!is_int($timestamp)) {
            throw new BridgeException('Lifecycle event must contain an integer timestamp.');
        }

        return $timestamp;
    }

    /** @param list<mixed> $arguments */
    private function stringResult(string $method, array $arguments, string $label): string
    {
        $value = $this->session->invoke($method, $arguments);
        if (!is_string($value)) {
            throw new BridgeException($label.' must be a string.');
        }

        return $value;
    }

    /** @return list<Label> */
    private function labels(mixed $value, string $label): array
    {
        return array_map(
            fn (array $payload): Label => new Label($this->session, $payload),
            Payload::objects($value, $label),
        );
    }

    /** @return list<MembershipRequestActionResult> */
    private function membershipRequestAction(
        string $groupId,
        MembershipRequestAction $action,
        ?MembershipRequestActionOptions $options,
    ): array {
        return array_map(
            static fn (array $payload): MembershipRequestActionResult => MembershipRequestActionResult::fromPayload($payload),
            Payload::objects($this->session->invoke('membershipRequestAction', [
                $groupId,
                $action->value,
                ($options ?? new MembershipRequestActionOptions())->toBridge(),
            ]), 'Membership action results'),
        );
    }
}
