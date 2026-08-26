<?php

declare(strict_types=1);

use Pam\WhatsApp\Button;
use Pam\WhatsApp\Buttons;
use Pam\WhatsApp\AddParticipantsOptions;
use Pam\WhatsApp\Call;
use Pam\WhatsApp\Channel;
use Pam\WhatsApp\ChannelReactionSetting;
use Pam\WhatsApp\Chat;
use Pam\WhatsApp\ChannelSearchView;
use Pam\WhatsApp\ClientInfoPhone;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\ContactId;
use Pam\WhatsApp\CreateChannelOptions;
use Pam\WhatsApp\CreateChannelResult;
use Pam\WhatsApp\CreateGroupOptions;
use Pam\WhatsApp\CreateGroupResult;
use Pam\WhatsApp\GroupMembershipRequest;
use Pam\WhatsApp\GroupChat;
use Pam\WhatsApp\InviteV4Data;
use Pam\WhatsApp\ListMessage;
use Pam\WhatsApp\LocalWebCacheOptions;
use Pam\WhatsApp\Location;
use Pam\WhatsApp\LocationSendOptions;
use Pam\WhatsApp\MediaFromURLOptions;
use Pam\WhatsApp\MessageMedia;
use Pam\WhatsApp\Message;
use Pam\WhatsApp\MessageContentType;
use Pam\WhatsApp\MessageType;
use Pam\WhatsApp\MembershipRequestActionOptions;
use Pam\WhatsApp\MembershipRequestActionResult;
use Pam\WhatsApp\MembershipRequestMethod;
use Pam\WhatsApp\MessageEditOptions;
use Pam\WhatsApp\MessageSearchOptions;
use Pam\WhatsApp\MessageSendChannelOptions;
use Pam\WhatsApp\MessageSendOptions;
use Pam\WhatsApp\NoWebCacheOptions;
use Pam\WhatsApp\Poll;
use Pam\WhatsApp\PollSendOptions;
use Pam\WhatsApp\ProductMetadata;
use Pam\WhatsApp\RemoteWebCacheOptions;
use Pam\WhatsApp\SearchChannelsOptions;
use Pam\WhatsApp\ScheduledEvent;
use Pam\WhatsApp\ScheduledEventCallType;
use Pam\WhatsApp\ScheduledEventResponse;
use Pam\WhatsApp\ScheduledEventSendOptions;
use Pam\WhatsApp\SelectedPollOption;
use Pam\WhatsApp\TransferChannelOwnershipOptions;
use Pam\WhatsApp\UnsubscribeOptions;
use Pam\WhatsApp\Auth\NoAuth;
use Pam\WhatsApp\Bridge\BridgeEvent;
use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\DisconnectionReason;
use Pam\WhatsApp\EventType;
use Pam\WhatsApp\GroupNotificationType;

require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Support/CertificationReport.php';
require dirname(__DIR__).'/Support/MatrixCertification.php';

final class ContractRecordingSession implements Session
{
    /** @var list<array{method: string, arguments: list<mixed>}> */
    public array $invocations = [];

    /** @var list<mixed> */
    public array $results = [];

    /** @var list<MessageData> */
    public array $contentResults = [];

    public ?Closure $listener = null;

    public bool $loggedOut = false;

    public function initialize(callable $listener): void
    {
        $this->listener = Closure::fromCallable($listener);
    }

    public function emit(BridgeEvent $event): void
    {
        ($this->listener ?? throw new LogicException('Contract session is not initialized.'))($event);
    }

    public function sendText(string $chatId, string $body, ?string $quotedMessageId = null): MessageData
    {
        throw new LogicException('Contract session does not send messages.');
    }

    public function sendContent(string $chatId, array $content, array $options = []): MessageData
    {
        $this->invocations[] = ['method' => 'sendContent', 'arguments' => [$chatId, $content, $options]];

        return array_shift($this->contentResults)
            ?? throw new LogicException('No contract content result is available.');
    }

    public function invoke(string $method, array $arguments = []): mixed
    {
        $this->invocations[] = ['method' => $method, 'arguments' => $arguments];

        return $this->results === [] ? true : array_shift($this->results);
    }

    public function pump(float $timeoutSeconds): bool
    {
        return false;
    }

    public function logout(): void
    {
        $this->loggedOut = true;
    }

    public function close(): void {}
}

/** @return array<string, mixed> */
function contractMessagePayload(string $id = 'event-message'): array
{
    return [
        'id' => $id,
        'chatId' => '5511999999999@c.us',
        'from' => '5511999999999@c.us',
        'to' => '5511888888888@c.us',
        'body' => 'PAM event certification',
        'fromMe' => false,
        'timestamp' => 1_700_000_000,
        'type' => MessageType::Text->value,
        'contentType' => MessageContentType::Text->value,
    ];
}

$matrix = new MatrixCertification(dirname(__DIR__, 2).'/api-matrix.json');
$report = new CertificationReport($matrix->knownEntries());

$report->check('symbols.public-contract', $matrix->symbolCoverage(), static function () use ($matrix): void {
    $matrix->assertSymbolContracts();
});

$report->check('enums.public-contract', $matrix->enumCoverage(), static function () use ($matrix): void {
    $matrix->assertEnumContracts();
});

$report->check('content.location', array_merge([
    'member:Location:1:constructor',
], $matrix->propertyCoverage('Location')), static function () use ($matrix): void {
    $location = new Location(-23.5505, -46.6333, new LocationSendOptions(
        name: 'São Paulo',
        address: 'Praça da Sé',
        url: 'https://example.test/location',
    ));
    $matrix->assertProperties($location, 'Location');
    if (($location->toBridge()['kind'] ?? null) !== \Pam\WhatsApp\ContentKind::Location->value) {
        throw new RuntimeException('Location bridge payload is invalid.');
    }
});

$report->check('content.poll', array_merge([
    'member:Poll:1:constructor',
], $matrix->propertyCoverage('Poll')), static function () use ($matrix): void {
    $poll = new Poll('Choose one', ['First', 'Second'], new PollSendOptions(
        allowMultipleAnswers: true,
        messageSecret: range(0, 31),
    ));
    $matrix->assertProperties($poll, 'Poll');
    if (count($poll->pollOptions) !== 2 || ($poll->toBridge()['allowMultipleAnswers'] ?? null) !== true) {
        throw new RuntimeException('Poll contract did not preserve its options.');
    }
});

$report->check('content.buttons', array_merge([
    'member:Buttons:1:constructor',
], $matrix->propertyCoverage('Buttons')), static function () use ($matrix): void {
    $buttons = new Buttons('Select', [new Button('Confirm', 'confirm')], 'Title', 'Footer');
    $matrix->assertProperties($buttons, 'Buttons');
    $payload = $buttons->toBridge();
    $specifications = $payload['buttons'] ?? null;
    $first = is_array($specifications) ? ($specifications[0] ?? null) : null;
    if (!is_array($first) || ($first['id'] ?? null) !== 'confirm') {
        throw new RuntimeException('Buttons contract did not preserve its identifier.');
    }
});

$report->check('content.list', array_merge([
    'member:List:1:constructor',
], $matrix->propertyCoverage('List')), static function () use ($matrix): void {
    $list = new ListMessage('Select', 'Open', [[
        'title' => 'Options',
        'rows' => [['id' => 'first', 'title' => 'First']],
    ]], 'Title', 'Footer');
    $matrix->assertProperties($list, 'List');
    $payload = $list->toBridge();
    $sections = $payload['sections'] ?? null;
    $firstSection = is_array($sections) ? ($sections[0] ?? null) : null;
    $rows = is_array($firstSection) ? ($firstSection['rows'] ?? null) : null;
    $firstRow = is_array($rows) ? ($rows[0] ?? null) : null;
    if (!is_array($firstRow) || ($firstRow['id'] ?? null) !== 'first') {
        throw new RuntimeException('List contract did not preserve its sections.');
    }
});

$report->check('content.scheduled-event', array_merge([
    'member:ScheduledEvent:1:constructor',
], $matrix->propertyCoverage('ScheduledEvent')), static function () use ($matrix): void {
    $start = new DateTimeImmutable('2030-01-01T12:00:00+00:00');
    $event = new ScheduledEvent('Community call', $start, new ScheduledEventSendOptions(
        description: 'PAM certification',
        endTime: $start->modify('+1 hour'),
        location: 'Online',
        messageSecret: range(0, 31),
    ));
    $matrix->assertProperties($event, 'ScheduledEvent');
    if (($event->toBridge()['startTime'] ?? null) !== $start->getTimestamp()) {
        throw new RuntimeException('Scheduled event timestamp was not preserved.');
    }
});

$report->check('media.factories', array_merge([
    'member:MessageMedia:1:fromFilePath',
    'member:MessageMedia:1:fromUrl',
], $matrix->propertyCoverage('MediaFromURLOptions')), static function () use ($matrix): void {
    $temporaryFile = tempnam(sys_get_temp_dir(), 'pam-wweb-media-certification-');
    if (!is_string($temporaryFile)) throw new RuntimeException('Unable to create a media fixture.');
    try {
        $content = 'PAM media certification';
        if (file_put_contents($temporaryFile, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('Unable to write the media fixture.');
        }
        $fromFile = MessageMedia::fromFilePath($temporaryFile);
        $options = new MediaFromURLOptions(unsafeMime: true, filename: 'certification.txt');
        $matrix->assertProperties($options, 'MediaFromURLOptions');
        $fromUrl = MessageMedia::fromUrl('data:text/plain;base64,'.base64_encode($content), $options);
        if (base64_decode($fromFile->data, true) !== $content || base64_decode($fromUrl->data, true) !== $content) {
            throw new RuntimeException('Media factories did not preserve the fixture bytes.');
        }
    } finally {
        if (is_file($temporaryFile) && !unlink($temporaryFile)) {
            throw new RuntimeException('Unable to remove the media fixture.');
        }
    }
});

$report->check('options.message', array_merge(
    $matrix->propertyCoverage('MessageSendOptions'),
    $matrix->propertyCoverage('MessageEditOptions'),
    $matrix->propertyCoverage('MessageSearchOptions'),
    $matrix->propertyCoverage('MessageSendChannelOptions'),
), static function () use ($matrix): void {
    $media = new MessageMedia('text/plain', base64_encode('options'), 'options.txt', 7);
    $send = new MessageSendOptions(
        sendSeen: false,
        caption: 'Caption',
        mentions: ['5511999999999@c.us'],
        media: $media,
        extra: ['custom' => true],
        stickerCategories: ['✅'],
    );
    $edit = new MessageEditOptions(false, ['5511999999999@c.us'], ['custom' => true]);
    $search = new MessageSearchOptions(25, true);
    $channel = new MessageSendChannelOptions('Caption', ['5511999999999@c.us'], $media, ['custom' => true]);
    $matrix->assertProperties($send, 'MessageSendOptions');
    $matrix->assertProperties($edit, 'MessageEditOptions');
    $matrix->assertProperties($search, 'MessageSearchOptions');
    $matrix->assertProperties($channel, 'MessageSendChannelOptions');
    if (($send->toBridge()['sendSeen'] ?? null) !== false
        || $edit->toBridge()['linkPreview'] !== false
        || $search->toBridge() !== ['limit' => 25, 'fromMe' => true]
        || ($channel->toBridge()['caption'] ?? null) !== 'Caption'
    ) {
        throw new RuntimeException('Message option bridge contracts did not preserve their values.');
    }
});

$report->check('options.group-channel', array_merge(
    $matrix->propertyCoverage('CreateGroupOptions'),
    $matrix->propertyCoverage('AddParticipantsOptions'),
    $matrix->propertyCoverage('MembershipRequestActionOptions'),
    $matrix->propertyCoverage('CreateChannelOptions'),
    $matrix->propertyCoverage('TransferChannelOwnershipOptions'),
    $matrix->propertyCoverage('UnsubscribeOptions'),
    $matrix->propertyCoverage('SearchChannelsOptions'),
), static function () use ($matrix): void {
    $group = new CreateGroupOptions(86_400, 'parent@g.us', false, 'Welcome', true, true, false, true);
    $participants = new AddParticipantsOptions([10, 20], false, 'Join us');
    $membership = new MembershipRequestActionOptions(['5511999999999@c.us'], [10, 20]);
    $picture = new MessageMedia('image/png', base64_encode('png'), 'channel.png', 3);
    $channel = new CreateChannelOptions('Community news', $picture);
    $transfer = new TransferChannelOwnershipOptions(true);
    $unsubscribe = new UnsubscribeOptions(true);
    $search = new SearchChannelsOptions(' pam ', ['br'], true, ChannelSearchView::Trending, 20);
    $matrix->assertProperties($group, 'CreateGroupOptions');
    $matrix->assertProperties($participants, 'AddParticipantsOptions');
    $matrix->assertProperties($membership, 'MembershipRequestActionOptions');
    $matrix->assertProperties($channel, 'CreateChannelOptions');
    $matrix->assertProperties($transfer, 'TransferChannelOwnershipOptions');
    $matrix->assertProperties($unsubscribe, 'UnsubscribeOptions');
    $matrix->assertProperties($search, 'SearchChannelsOptions');
    if (($group->toBridge()['messageTimer'] ?? null) !== 86_400
        || $participants->toBridge()['sleep'] !== [10, 20]
        || ($membership->toBridge()['requesterIds'] ?? null) !== ['5511999999999@c.us']
        || ($channel->toBridge()['description'] ?? null) !== 'Community news'
        || $transfer->toBridge() !== ['shouldDismissSelfAsAdmin' => true]
        || $unsubscribe->toBridge() !== ['deleteLocalModels' => true]
        || $search->toBridge()['countryCodes'] !== ['BR']
    ) {
        throw new RuntimeException('Group or channel option bridge contracts did not preserve their values.');
    }
});

$report->check('options.content', array_merge(
    $matrix->propertyCoverage('ScheduledEventSendOptions'),
    $matrix->propertyCoverage('LocationSendOptions'),
    $matrix->propertyCoverage('PollSendOptions'),
), static function () use ($matrix): void {
    $start = new DateTimeImmutable('2030-01-01T12:00:00+00:00');
    $scheduled = new ScheduledEventSendOptions(
        description: 'Description',
        endTime: $start,
        location: 'Online',
        messageSecret: range(0, 31),
    );
    $location = new LocationSendOptions('Place', 'Address', 'https://example.test');
    $poll = new PollSendOptions(true, range(0, 31));
    $matrix->assertProperties($scheduled, 'ScheduledEventSendOptions');
    $matrix->assertProperties($location, 'LocationSendOptions');
    $matrix->assertProperties($poll, 'PollSendOptions');
});

$report->check('options.client-cache', array_merge(
    $matrix->propertyCoverage('ClientOptions'),
    $matrix->propertyCoverage('LocalWebCacheOptions'),
    $matrix->propertyCoverage('RemoteWebCacheOptions'),
    $matrix->propertyCoverage('NoWebCacheOptions'),
), static function () use ($matrix): void {
    $local = new LocalWebCacheOptions('/tmp/pam-wweb-cache', true);
    $remote = new RemoteWebCacheOptions('https://example.test/{version}.html', true, 5.0);
    $none = new NoWebCacheOptions();
    $client = new ClientOptions(
        browserExecutable: '/usr/bin/chromium',
        sessionDirectory: '/tmp/pam-wweb-session',
        headless: false,
        browserTimeoutSeconds: 15.0,
        authenticationTimeoutSeconds: 45.0,
        browserArguments: ['--disable-dev-shm-usage'],
        browserName: 'Chrome',
        bypassCSP: true,
        deviceName: 'PAM',
        ffmpegPath: '/usr/bin/ffmpeg',
        qrMaxRetries: 3,
        takeoverOnConflict: true,
        takeoverTimeoutMs: 5_000,
        userAgent: 'PAM certification',
        webVersionCache: $local,
    );
    $matrix->assertProperties($local, 'LocalWebCacheOptions');
    $matrix->assertProperties($remote, 'RemoteWebCacheOptions');
    $matrix->assertProperties($none, 'NoWebCacheOptions');
    $matrix->assertProperties($client, 'ClientOptions');
    if ($client->effectiveBrowserExecutable() !== '/usr/bin/chromium'
        || $client->effectiveHeadless()
        || $client->effectiveBrowserTimeoutSeconds() !== 15.0
        || $client->effectiveAuthenticationTimeoutSeconds() !== 45.0
        || $client->effectiveSessionDirectory() !== '/tmp/pam-wweb-session'
    ) {
        throw new RuntimeException('Client option effective values are invalid.');
    }
});

$report->check('payload.client-commerce', array_merge(
    $matrix->propertyCoverage('ClientInfoPhone'),
    $matrix->propertyCoverage('ProductMetadata'),
    $matrix->propertyCoverage('BatteryInfo'),
), static function () use ($matrix): void {
    $phone = new ClientInfoPhone([
        'wa_version' => '1.34.7',
        'os_version' => 'Linux',
        'device_manufacturer' => 'PAM',
        'device_model' => 'CDP',
        'os_build_number' => '1',
    ]);
    $product = new ProductMetadata([
        'id' => 'product-1',
        'name' => 'Product',
        'description' => 'Description',
        'retailer_id' => 'retailer-1',
    ]);
    $battery = new \Pam\WhatsApp\Event\BatteryChanged(87, true);
    $matrix->assertProperties($phone, 'ClientInfoPhone');
    $matrix->assertProperties($product, 'ProductMetadata');
    $matrix->assertProperties($battery, 'BatteryInfo');
    if ($phone->wa_version !== '1.34.7' || $product->retailer_id !== 'retailer-1' || $battery->battery !== 87) {
        throw new RuntimeException('Client, commerce, or battery payload normalization failed.');
    }
});

$report->check('payload.group-channel-results', array_merge(
    $matrix->propertyCoverage('GroupMembershipRequest'),
    $matrix->propertyCoverage('CreateChannelResult'),
    $matrix->propertyCoverage('CreateGroupResult'),
    $matrix->propertyCoverage('MembershipRequestActionResult'),
), static function () use ($matrix): void {
    $request = new GroupMembershipRequest([
        'id' => ['server' => 'c.us', 'user' => '5511999999999', '_serialized' => '5511999999999@c.us'],
        'addedBy' => ['server' => 'c.us', 'user' => '5511888888888', '_serialized' => '5511888888888@c.us'],
        'parentGroupId' => ['server' => 'g.us', 'user' => '120000', '_serialized' => '120000@g.us'],
        'requestMethod' => MembershipRequestMethod::NonAdminAdd->value,
        'timestamp' => 1_700_000_000,
    ]);
    $channel = new CreateChannelResult([
        'title' => 'Community',
        'nid' => ['server' => 'newsletter', 'user' => '120001', '_serialized' => '120001@newsletter'],
        'inviteLink' => 'https://whatsapp.com/channel/example',
        'createdAtTs' => 1_700_000_001,
    ]);
    $group = new CreateGroupResult([
        'title' => 'Group',
        'gid' => ['server' => 'g.us', 'user' => '120002', '_serialized' => '120002@g.us'],
        'participants' => [
            '5511999999999@c.us' => [
                'statusCode' => 200,
                'message' => 'OK',
                'isGroupCreator' => true,
                'isInviteV4Sent' => false,
            ],
        ],
    ]);
    $action = MembershipRequestActionResult::fromPayload([
        'requesterId' => ['5511999999999@c.us'],
        'error' => null,
        'message' => 'Approved',
    ]);
    $matrix->assertProperties($request, 'GroupMembershipRequest');
    $matrix->assertProperties($channel, 'CreateChannelResult');
    $matrix->assertProperties($group, 'CreateGroupResult');
    $matrix->assertProperties($action, 'MembershipRequestActionResult');
    $matrix->assertProperties($request->id, 'ContactId');
    $matrix->assertProperties($channel->nid, 'ContactId');
    $matrix->assertProperties($group->gid, 'ContactId');
    if ($request->requestMethod !== MembershipRequestMethod::NonAdminAdd
        || $channel->nid->serialized !== '120001@newsletter'
        || !isset($group->participants['5511999999999@c.us'])
        || $action->requesterId !== ['5511999999999@c.us']
    ) {
        throw new RuntimeException('Group or channel result normalization failed.');
    }
});

$report->check('payload.selected-poll-option', $matrix->propertyCoverage('SelectedPollOption'), static function () use ($matrix): void {
    $option = SelectedPollOption::fromPayload(['id' => 7, 'name' => 'Selected']);
    $matrix->assertProperties($option, 'SelectedPollOption');
    if ($option->id !== 7 || $option->name !== 'Selected') {
        throw new RuntimeException('Selected poll option normalization failed.');
    }
});

$report->check('payload.chat-id', $matrix->propertyCoverage('ChatId'), static function () use ($matrix): void {
    $id = ContactId::fromSerialized('5511999999999@c.us');
    $matrix->assertProperties($id, 'ChatId');
    if ($id->_serialized !== $id->serialized || $id->server !== 'c.us' || $id->user !== '5511999999999') {
        throw new RuntimeException('ChatId compatibility alias normalization failed.');
    }
});

$report->check('auth.default-decision', [
    'member:AuthStrategy:1:onAuthenticationNeeded',
], static function (): void {
    $decision = (new NoAuth())->onAuthenticationNeeded();
    if ($decision !== ['failed' => false, 'restart' => false, 'failureEventPayload' => null]) {
        throw new RuntimeException('Default authentication decision is incompatible.');
    }
});

$report->check('client.command-forwarding', [
    'member:Client:1:acceptInvite',
    'member:Client:1:requestPairingCode',
    'member:Client:1:cancelPairingCode',
    'member:Client:1:resetState',
    'member:Client:1:createCallLink',
    'member:Client:1:sendResponseToScheduledEvent',
    'member:Client:1:sendReaction',
    'member:Client:1:setAutoDownloadAudio',
    'member:Client:1:setAutoDownloadDocuments',
    'member:Client:1:setAutoDownloadPhotos',
    'member:Client:1:setAutoDownloadVideos',
    'member:Client:1:setBackgroundSync',
    'member:Client:1:saveOrEditAddressbookContact',
    'member:Client:1:deleteAddressbookContact',
    'member:Client:1:setStatus',
    'member:Client:1:setDisplayName',
], static function (): void {
    $session = new ContractRecordingSession();
    $session->results = [
        '120000@g.us',
        '12345678',
        true,
        true,
        'https://call.whatsapp.com/video/example',
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
    ];
    $client = Client::forSession($session);
    if ($client->acceptInvite('invite-code') !== '120000@g.us') {
        throw new RuntimeException('Invite acceptance did not preserve its result.');
    }
    if ($client->requestPairingCode('5511999999999', false, 90_000) !== '12345678') {
        throw new RuntimeException('Pairing-code request did not preserve its result.');
    }
    $client->cancelPairingCode();
    $client->resetState();
    $startsAt = new DateTimeImmutable('2030-01-01T12:00:00+00:00');
    if ($client->createCallLink($startsAt, ScheduledEventCallType::Video) !== 'https://call.whatsapp.com/video/example') {
        throw new RuntimeException('Call-link creation did not normalize its bridge result.');
    }
    if (!$client->sendResponseToScheduledEvent(ScheduledEventResponse::Going, 'event-message')) {
        throw new RuntimeException('Scheduled-event response did not preserve its bridge result.');
    }
    $client->sendReaction('message-id', '✅');
    $client->setAutoDownloadAudio(false);
    $client->setAutoDownloadDocuments(true);
    $client->setAutoDownloadPhotos(false);
    $client->setAutoDownloadVideos(true);
    $client->setBackgroundSync(false);
    $client->saveOrEditAddressbookContact('5511999999999', 'PAM', 'Certification', false);
    $client->deleteAddressbookContact('5511999999999');
    $client->setStatus('PAM certification');
    if (!$client->setDisplayName('PAM')) {
        throw new RuntimeException('Display-name update did not preserve its bridge result.');
    }

    $expected = [
        ['method' => 'acceptInvite', 'arguments' => ['invite-code']],
        ['method' => 'requestPairingCode', 'arguments' => ['5511999999999', false, 90_000]],
        ['method' => 'cancelPairingCode', 'arguments' => []],
        ['method' => 'resetState', 'arguments' => []],
        ['method' => 'createCallLink', 'arguments' => [$startsAt->getTimestamp(), ScheduledEventCallType::Video->value]],
        ['method' => 'sendResponseToScheduledEvent', 'arguments' => [ScheduledEventResponse::Going->value, 'event-message']],
        ['method' => 'sendReaction', 'arguments' => ['message-id', '✅']],
        ['method' => 'setAutoDownload', 'arguments' => ['audio', false]],
        ['method' => 'setAutoDownload', 'arguments' => ['documents', true]],
        ['method' => 'setAutoDownload', 'arguments' => ['photos', false]],
        ['method' => 'setAutoDownload', 'arguments' => ['videos', true]],
        ['method' => 'setBackgroundSync', 'arguments' => [false]],
        ['method' => 'saveOrEditAddressbookContact', 'arguments' => ['5511999999999', 'PAM', 'Certification', false]],
        ['method' => 'deleteAddressbookContact', 'arguments' => ['5511999999999']],
        ['method' => 'setStatus', 'arguments' => ['PAM certification']],
        ['method' => 'setDisplayName', 'arguments' => ['PAM']],
    ];
    if ($session->invocations !== $expected) {
        throw new RuntimeException('Client command forwarding did not preserve the bridge contract.');
    }
});

$report->check('client.profile-and-logout', [
    'member:Client:1:setProfilePicture',
    'member:Client:1:deleteProfilePicture',
    'member:Client:1:logout',
], static function (): void {
    $session = new ContractRecordingSession();
    $session->results = [true, true];
    $client = Client::forSession($session);
    $client->initialize();
    $session->emit(new BridgeEvent(EventType::Ready, [
        'timestamp' => 1_700_000_000,
        'info' => [
            'wid' => ['server' => 'c.us', 'user' => '5511888888888', '_serialized' => '5511888888888@c.us'],
            'platform' => 'web',
            'pushname' => 'PAM',
        ],
    ]));
    $picture = new MessageMedia('image/png', base64_encode('png'), 'profile.png', 3);
    if (!$client->setProfilePicture($picture) || !$client->deleteProfilePicture()) {
        throw new RuntimeException('Profile-picture command result was not preserved.');
    }
    $client->logout();
    if (!$session->loggedOut || $client->state !== \Pam\WhatsApp\ClientState::Closed) {
        throw new RuntimeException('Client logout did not close the session and client state.');
    }
    if (array_column($session->invocations, 'method') !== ['setProfilePicture', 'deleteProfilePicture']) {
        throw new RuntimeException('Profile-picture forwarding did not preserve the bridge contract.');
    }
    if (($session->invocations[0]['arguments'][0] ?? null) !== '5511888888888@c.us'
        || ($session->invocations[1]['arguments'] ?? null) !== ['5511888888888@c.us']
    ) {
        throw new RuntimeException('Profile-picture target did not use the authenticated client id.');
    }
});

$report->check('client.remaining-events', [
    'member:Client:3:auth_failure',
    'member:Client:3:call',
    'member:Client:3:change_battery',
    'member:Client:3:chat_removed',
    'member:Client:3:code',
    'member:Client:3:contact_changed',
    'member:Client:3:disconnected',
    'member:Client:3:group_admin_changed',
    'member:Client:3:group_join',
    'member:Client:3:group_leave',
    'member:Client:3:group_membership_request',
    'member:Client:3:message',
    'member:Client:3:message_ciphertext',
    'member:Client:3:message_ciphertext_failed',
    'member:Client:3:message_revoke_me',
    'member:Client:3:qr',
    'member:Client:3:remote_session_saved',
    'member:Client:3:unread_count',
    'member:Client:3:vote_update',
], static function (): void {
    $session = new ContractRecordingSession();
    $client = Client::forSession($session);
    /** @var array<int, object> $received */
    $received = [];
    $expected = [
        EventType::AuthenticationFailure->value => \Pam\WhatsApp\Event\ClientError::class,
        EventType::CallReceived->value => \Pam\WhatsApp\Event\CallReceived::class,
        EventType::BatteryChanged->value => \Pam\WhatsApp\Event\BatteryChanged::class,
        EventType::ChatRemoved->value => \Pam\WhatsApp\Event\ChatRemoved::class,
        EventType::PairingCodeReceived->value => \Pam\WhatsApp\Event\PairingCodeReceived::class,
        EventType::ContactChanged->value => \Pam\WhatsApp\Event\ContactChanged::class,
        EventType::Disconnected->value => \Pam\WhatsApp\Event\Disconnected::class,
        EventType::GroupAdminChanged->value => \Pam\WhatsApp\Event\GroupNotification::class,
        EventType::GroupJoined->value => \Pam\WhatsApp\Event\GroupNotification::class,
        EventType::GroupLeft->value => \Pam\WhatsApp\Event\GroupNotification::class,
        EventType::GroupMembershipRequest->value => \Pam\WhatsApp\Event\GroupNotification::class,
        EventType::MessageReceived->value => \Pam\WhatsApp\Event\MessageReceived::class,
        EventType::MessageCiphertext->value => \Pam\WhatsApp\Event\MessageLifecycle::class,
        EventType::MessageCiphertextFailed->value => \Pam\WhatsApp\Event\MessageLifecycle::class,
        EventType::MessageRevokedMe->value => \Pam\WhatsApp\Event\MessageLifecycle::class,
        EventType::QrCode->value => \Pam\WhatsApp\Event\QrCodeReceived::class,
        EventType::RemoteSessionSaved->value => \Pam\WhatsApp\Event\RemoteSessionSaved::class,
        EventType::UnreadCountChanged->value => \Pam\WhatsApp\Event\UnreadCountChanged::class,
        EventType::VoteUpdated->value => \Pam\WhatsApp\PollVote::class,
    ];
    foreach ($expected as $eventValue => $_expectedClass) {
        $type = EventType::from($eventValue);
        $client->on($type, static function (object $event) use (&$received, $eventValue): void {
            $received[$eventValue] = $event;
        });
    }
    $client->initialize();
    $message = contractMessagePayload();
    $group = [
        'id' => 'notification-id', 'author' => 'admin@c.us', 'body' => 'Group changed',
        'chatId' => '120000@g.us', 'recipientIds' => ['member@c.us'], 'timestamp' => 1_700_000_001,
        'type' => GroupNotificationType::Add->value,
    ];
    $events = [
        new BridgeEvent(EventType::AuthenticationFailure, ['message' => 'authentication failed']),
        new BridgeEvent(EventType::CallReceived, [
            'id' => 'call-id', 'from' => 'caller@c.us', 'timestamp' => 1_700_000_002, 'isVideo' => true,
            'isGroup' => false, 'fromMe' => false, 'canHandleLocally' => true,
            'webClientShouldHandle' => true, 'participants' => ['caller@c.us'],
        ]),
        new BridgeEvent(EventType::BatteryChanged, ['battery' => 87, 'plugged' => true]),
        new BridgeEvent(EventType::ChatRemoved, ['chatId' => 'removed@c.us']),
        new BridgeEvent(EventType::PairingCodeReceived, ['code' => '12345678']),
        new BridgeEvent(EventType::ContactChanged, [
            'message' => $message, 'oldId' => 'old@c.us', 'newId' => 'new@c.us', 'isContact' => true,
        ]),
        new BridgeEvent(EventType::GroupAdminChanged, $group),
        new BridgeEvent(EventType::GroupJoined, $group),
        new BridgeEvent(EventType::GroupLeft, $group),
        new BridgeEvent(EventType::GroupMembershipRequest, $group),
        new BridgeEvent(EventType::MessageReceived, $message),
        new BridgeEvent(EventType::MessageCiphertext, ['message' => $message]),
        new BridgeEvent(EventType::MessageCiphertextFailed, ['message' => $message]),
        new BridgeEvent(EventType::MessageRevokedMe, ['message' => $message]),
        new BridgeEvent(EventType::QrCode, ['code' => 'qr-code']),
        new BridgeEvent(EventType::RemoteSessionSaved, ['timestamp' => 1_700_000_003]),
        new BridgeEvent(EventType::UnreadCountChanged, ['chatId' => 'chat@c.us', 'unreadCount' => 3]),
        new BridgeEvent(EventType::VoteUpdated, ['vote' => [
            'voter' => 'member@c.us', 'selectedOptions' => [['id' => 1, 'name' => 'First']],
            'interractedAtTs' => 1_700_000_004, 'parentMessage' => array_replace($message, [
                'type' => MessageType::PollCreation->value, 'contentType' => MessageContentType::Poll->value,
            ]),
        ]]),
        new BridgeEvent(EventType::Disconnected, ['reason' => DisconnectionReason::Conflict->value]),
    ];
    foreach ($events as $event) $session->emit($event);
    foreach ($expected as $eventValue => $expectedClass) {
        $event = $received[$eventValue] ?? null;
        if (!$event instanceof $expectedClass) {
            throw new RuntimeException('Event did not hydrate its public type: '.EventType::from($eventValue)->name);
        }
    }
    if (count($received) !== 19) {
        throw new RuntimeException('Not every remaining public event was dispatched exactly once.');
    }
});

$report->check('chat.command-forwarding', [
    'member:Chat:1:addOrEditCustomerNote',
    'member:Chat:1:changeLabels',
    'member:Chat:1:clearMessages',
    'member:Chat:1:delete',
], static function (): void {
    $session = new ContractRecordingSession();
    $session->results = [true, true, true, true];
    $chat = new Chat($session, [
        'id' => '5511999999999@c.us',
        'name' => 'Certification chat',
    ]);
    $chat->addOrEditCustomerNote('Remember this contact');
    $chat->changeLabels([1, '2']);
    if (!$chat->clearMessages() || !$chat->delete()) {
        throw new RuntimeException('Chat command result was not preserved.');
    }
    if ($session->invocations !== [
        ['method' => 'addOrEditCustomerNote', 'arguments' => ['5511999999999@c.us', 'Remember this contact']],
        ['method' => 'addOrRemoveLabels', 'arguments' => [[1, '2'], ['5511999999999@c.us']]],
        ['method' => 'clearMessages', 'arguments' => ['5511999999999@c.us']],
        ['method' => 'deleteChat', 'arguments' => ['5511999999999@c.us']],
    ]) {
        throw new RuntimeException('Chat command forwarding did not preserve the bridge contract.');
    }
});

$report->check('client.domain-command-forwarding', [
    'member:Client:1:acceptChannelAdminInvite',
    'member:Client:1:acceptGroupV4Invite',
    'member:Client:1:addOrEditCustomerNote',
    'member:Client:1:addOrRemoveLabels',
    'member:Client:1:approveGroupMembershipRequests',
    'member:Client:1:createChannel',
    'member:Client:1:createGroup',
    'member:Client:1:deleteChannel',
    'member:Client:1:demoteChannelAdmin',
    'member:Client:1:getChannelByInviteCode',
    'member:Client:1:getPollVotes',
    'member:Client:1:rejectGroupMembershipRequests',
    'member:Client:1:revokeChannelAdminInvite',
    'member:Client:1:revokeStatusMessage',
    'member:Client:1:sendChannelAdminInvite',
    'member:Client:1:subscribeToChannel',
    'member:Client:1:transferChannelOwnership',
    'member:Client:1:unsubscribeFromChannel',
], static function (): void {
    $session = new ContractRecordingSession();
    $session->results = [
        ['id' => '120001@newsletter', 'name' => 'Channel', 'isChannel' => true],
        'created-channel',
        'created-group',
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        true,
        [],
        [],
        ['status' => 200],
        [],
    ];
    $client = Client::forSession($session);
    if (!$client->getChannelByInviteCode('channel-code') instanceof Channel
        || $client->createChannel('Community') !== 'created-channel'
        || $client->createGroup('Group', ['5511999999999@c.us']) !== 'created-group'
        || !$client->subscribeToChannel('120001@newsletter')
        || !$client->unsubscribeFromChannel('120001@newsletter')
        || !$client->transferChannelOwnership('120001@newsletter', '5511999999999@c.us')
        || !$client->deleteChannel('120001@newsletter')
        || !$client->sendChannelAdminInvite('5511888888888@c.us', '120001@newsletter')
        || !$client->acceptChannelAdminInvite('120001@newsletter')
        || !$client->revokeChannelAdminInvite('120001@newsletter', '5511999999999@c.us')
        || !$client->demoteChannelAdmin('120001@newsletter', '5511999999999@c.us')
    ) {
        throw new RuntimeException('Client domain command result was not preserved.');
    }
    $client->addOrRemoveLabels([1], ['5511888888888@c.us']);
    $client->addOrEditCustomerNote('5511888888888@c.us', 'Certification note');
    $client->revokeStatusMessage('status-message');
    if ($client->approveGroupMembershipRequests('120000@g.us') !== []
        || $client->rejectGroupMembershipRequests('120000@g.us') !== []
    ) {
        throw new RuntimeException('Client membership action result was not preserved.');
    }
    $invite = new InviteV4Data(
        'invite-code', 1_800_000_000, '120000@g.us', 'Group', '5511888888888@c.us', '5511999999999@c.us',
    );
    if ($client->acceptGroupV4Invite($invite)->status !== 200 || $client->getPollVotes('poll-message') !== []) {
        throw new RuntimeException('Client invite or poll result was not preserved.');
    }
    if (array_column($session->invocations, 'method') !== [
        'getChannelByInviteCode', 'createChannel', 'createGroup', 'subscribeToChannel', 'unsubscribeFromChannel',
        'transferChannelOwnership', 'deleteChannel', 'sendChannelAdminInvite', 'acceptChannelAdminInvite',
        'revokeChannelAdminInvite', 'demoteChannelAdmin', 'addOrRemoveLabels', 'addOrEditCustomerNote',
        'revokeStatusMessage', 'membershipRequestAction', 'membershipRequestAction', 'acceptGroupV4Invite', 'getPollVotes',
    ]) {
        throw new RuntimeException('Client domain command forwarding did not preserve the bridge contract.');
    }
    if (($session->invocations[2]['arguments'] ?? null) !== ['Group', ['5511999999999@c.us'], []]
        || ($session->invocations[14]['arguments'] ?? null) !== [
            '120000@g.us', 1, (new MembershipRequestActionOptions())->toBridge(),
        ]
        || ($session->invocations[15]['arguments'] ?? null) !== [
            '120000@g.us', 2, (new MembershipRequestActionOptions())->toBridge(),
        ]
        || ($session->invocations[16]['arguments'] ?? null) !== [$invite->toBridge()]
    ) {
        throw new RuntimeException('Client domain command arguments did not preserve their option contracts.');
    }
});

$report->check('group-chat.command-forwarding', [
    'member:GroupChat:1:addParticipants',
    'member:GroupChat:1:removeParticipants',
    'member:GroupChat:1:setAddMembersAdminsOnly',
    'member:GroupChat:1:setMessagesAdminsOnly',
    'member:GroupChat:1:setInfoAdminsOnly',
    'member:GroupChat:1:deletePicture',
    'member:GroupChat:1:setPicture',
    'member:GroupChat:1:revokeInvite',
    'member:GroupChat:1:leave',
    'member:GroupChat:1:approveGroupMembershipRequests',
    'member:GroupChat:1:rejectGroupMembershipRequests',
], static function (): void {
    $session = new ContractRecordingSession();
    $session->results = ['already-member', ['status' => 200], true, true, true, true, true, true, true, [], []];
    $group = new GroupChat($session, [
        'id' => '120000@g.us',
        'name' => 'Certification group',
        'isGroup' => true,
        'groupMetadata' => ['creation' => 1_700_000_000, 'desc' => 'Group', 'participants' => []],
    ]);
    if ($group->addParticipants(['5511999999999@c.us']) !== 'already-member') {
        throw new RuntimeException('Add-participant string result was not preserved.');
    }
    if ($group->removeParticipants(['5511888888888@c.us'])->status !== 200
        || !$group->setAddMembersAdminsOnly(false)
        || !$group->setMessagesAdminsOnly()
        || !$group->setInfoAdminsOnly(false)
        || !$group->deletePicture()
        || !$group->setPicture(new MessageMedia('image/png', base64_encode('png'), 'group.png', 3))
    ) {
        throw new RuntimeException('Group command result was not preserved.');
    }
    $group->revokeInvite();
    $group->leave();
    if ($group->approveGroupMembershipRequests() !== [] || $group->rejectGroupMembershipRequests() !== []) {
        throw new RuntimeException('Group membership action result was not preserved.');
    }
    $invocations = array_column($session->invocations, 'method');
    if ($invocations !== [
        'addGroupParticipants', 'modifyGroupParticipants', 'setGroupSetting', 'setGroupSetting',
        'setGroupSetting', 'deleteGroupPicture', 'setGroupPicture', 'revokeGroupInvite',
        'leaveGroup', 'membershipRequestAction', 'membershipRequestAction',
    ]) {
        throw new RuntimeException('Group command forwarding did not preserve the bridge contract.');
    }
    if (($session->invocations[1]['arguments'] ?? null) !== ['120000@g.us', 1, ['5511888888888@c.us']]
        || ($session->invocations[2]['arguments'] ?? null) !== ['120000@g.us', 1, false]
        || ($session->invocations[3]['arguments'] ?? null) !== ['120000@g.us', 2, true]
        || ($session->invocations[4]['arguments'] ?? null) !== ['120000@g.us', 3, false]
    ) {
        throw new RuntimeException('Group action arguments did not preserve their enum contracts.');
    }
});

$report->check('channel.command-forwarding', [
    'member:Channel:1:acceptChannelAdminInvite',
    'member:Channel:1:deleteChannel',
    'member:Channel:1:demoteChannelAdmin',
    'member:Channel:1:revokeChannelAdminInvite',
    'member:Channel:1:sendChannelAdminInvite',
    'member:Channel:1:setProfilePicture',
    'member:Channel:1:setReactionSetting',
    'member:Channel:1:transferChannelOwnership',
], static function (): void {
    $session = new ContractRecordingSession();
    $session->results = array_fill(0, 8, true);
    $channel = new Channel($session, ['id' => '120001@newsletter', 'name' => 'Certification channel', 'isChannel' => true]);
    $picture = new MessageMedia('image/png', base64_encode('png'), 'channel.png', 3);
    if (!$channel->acceptChannelAdminInvite()
        || !$channel->deleteChannel()
        || !$channel->demoteChannelAdmin('5511999999999@c.us')
        || !$channel->revokeChannelAdminInvite('5511999999999@c.us')
        || !$channel->sendChannelAdminInvite('5511888888888@c.us')
        || !$channel->setProfilePicture($picture)
        || !$channel->setReactionSetting(ChannelReactionSetting::All)
        || !$channel->transferChannelOwnership('5511999999999@c.us')
    ) {
        throw new RuntimeException('Channel command result was not preserved.');
    }
    if (array_column($session->invocations, 'method') !== [
        'acceptChannelAdminInvite', 'deleteChannel', 'demoteChannelAdmin', 'revokeChannelAdminInvite',
        'sendChannelAdminInvite', 'setChannelMetadata', 'setChannelReactionSetting', 'transferChannelOwnership',
    ]) {
        throw new RuntimeException('Channel command forwarding did not preserve the bridge contract.');
    }
    if (($session->invocations[6]['arguments'] ?? null) !== ['120001@newsletter', ChannelReactionSetting::All->value]
        || ($session->invocations[7]['arguments'] ?? null) !== ['120001@newsletter', '5511999999999@c.us', []]
    ) {
        throw new RuntimeException('Channel command arguments did not preserve their option contracts.');
    }
});

$report->check('message.command-forwarding', [
    'member:Message:1:acceptGroupV4Invite',
    'member:Message:1:editScheduledEvent',
    'member:Message:1:forward',
    'member:Message:1:vote',
], static function (): void {
    $session = new ContractRecordingSession();
    $session->results = [true, ['status' => 200], true, null];
    $metadata = [
        'inviteV4' => [
            'inviteCode' => 'invite-code', 'inviteCodeExp' => 1_800_000_000, 'groupId' => '120000@g.us',
            'groupName' => 'Group', 'fromId' => '5511888888888@c.us', 'toId' => '5511999999999@c.us',
        ],
    ];
    $message = new Message($session, new MessageData(
        'message-id', '5511999999999@c.us', '5511999999999@c.us', '5511888888888@c.us', '', true,
        1_700_000_000, MessageType::GroupInvite, MessageContentType::Poll, $metadata,
    ));
    $message->forward('5511777777777@c.us');
    if ($message->acceptGroupV4Invite()->status !== 200) {
        throw new RuntimeException('Group invite acceptance did not preserve its result.');
    }
    $message->vote(['First']);
    $event = new ScheduledEvent('Updated event', new DateTimeImmutable('2030-01-01T12:00:00+00:00'));
    if ($message->editScheduledEvent($event) !== null) {
        throw new RuntimeException('Null scheduled-event edit result was not preserved.');
    }
    if (array_column($session->invocations, 'method') !== [
        'forwardMessage', 'acceptGroupV4Invite', 'voteMessage', 'editScheduledEvent',
    ]) {
        throw new RuntimeException('Message command forwarding did not preserve the bridge contract.');
    }
    if (($session->invocations[0]['arguments'] ?? null) !== ['message-id', '5511777777777@c.us']
        || ($session->invocations[2]['arguments'] ?? null) !== ['message-id', ['First']]
    ) {
        throw new RuntimeException('Message command arguments did not preserve the bridge contract.');
    }
});

$report->check('payload.call', array_merge([
    'member:Call:1:reject',
], $matrix->propertyCoverage('Call')), static function () use ($matrix): void {
    $session = new ContractRecordingSession();
    $call = new Call($session, [
        'id' => 'call-1',
        'from' => '5511999999999@c.us',
        'timestamp' => 1_700_000_000,
        'isVideo' => true,
        'isGroup' => false,
        'fromMe' => false,
        'canHandleLocally' => true,
        'webClientShouldHandle' => false,
        'participants' => ['5511999999999@c.us'],
    ]);
    $matrix->assertProperties($call, 'Call');
    $call->reject();
    if ($session->invocations !== [[
        'method' => 'rejectCall',
        'arguments' => ['5511999999999@c.us', 'call-1'],
    ]]) {
        throw new RuntimeException('Call rejection did not preserve the bridge contract.');
    }
});

$report->check('media.streaming-contract', array_merge([
    'member:Message:1:downloadMediaStream',
], $matrix->propertyCoverage('MediaStreamOptions'), $matrix->propertyCoverage('MessageMediaMetadata'), $matrix->propertyCoverage('MessageMediaStream')), static function () use ($matrix): void {
    $session = new ContractRecordingSession();
    $session->results = [
        [
            'token' => 'stream-token', 'blobSize' => 6, 'mimetype' => 'text/plain',
            'filename' => 'stream.txt', 'filesize' => 6,
        ],
        ['data' => base64_encode('abc'), 'done' => false],
        ['data' => base64_encode('def'), 'done' => true],
        true,
    ];
    $message = new Message($session, new MessageData(
        'stream-message', 'chat@c.us', 'sender@c.us', 'receiver@c.us', '', false,
        1_700_000_000, MessageType::Document, MessageContentType::Media, ['hasMedia' => true],
    ));
    $options = new \Pam\WhatsApp\MediaStreamOptions(3);
    $streamed = $message->downloadMediaStream($options);
    if ($streamed === null) throw new RuntimeException('Media stream was not opened.');
    $matrix->assertProperties($options, 'MediaStreamOptions');
    $matrix->assertProperties($streamed, 'MessageMediaMetadata');
    $matrix->assertProperties($streamed, 'MessageMediaStream');
    if (implode('', iterator_to_array($streamed->stream)) !== 'abcdef') {
        throw new RuntimeException('Media stream chunks did not preserve their bytes.');
    }
    if (array_column($session->invocations, 'method') !== [
        'openMessageMediaStream', 'readMessageMediaStream', 'readMessageMediaStream', 'closeMessageMediaStream',
    ]) {
        throw new RuntimeException('Media stream bridge lifecycle was not preserved.');
    }
});

$report->check('remaining.behavioral-contracts', $matrix->incompleteMemberCoverage(), static function () use ($matrix): void {
    $contactPayload = static fn (string $id): array => [
        'id' => $id,
        'number' => strstr($id, '@', true) ?: $id,
        'name' => 'PAM contact',
        'isUser' => true,
        'isWAContact' => true,
    ];
    $messageData = static fn (string $id = 'contract-message', MessageContentType $contentType = MessageContentType::Text, array $metadata = []): MessageData => new MessageData(
        $id,
        '5511999999999@c.us',
        '5511999999999@c.us',
        '5511888888888@c.us',
        'PAM contract',
        false,
        1_700_000_000,
        MessageType::Text,
        $contentType,
        $metadata,
    );

    $session = new ContractRecordingSession();
    $chat = new Chat($session, [
        'id' => '5511999999999@c.us', 'name' => 'Contract chat', 'isMuted' => false,
    ]);
    $session->results = [true, true, ['isMuted' => true, 'muteExpiration' => -1], true, true, true, true, ['isMuted' => false, 'muteExpiration' => 0], true];
    $chat->archive();
    $chat->markUnread();
    if (!$chat->mute()->isMuted || !$chat->pin()) throw new RuntimeException('Chat mute or pin contract failed.');
    $chat->sendStateRecording();
    if (!$chat->syncHistory()) throw new RuntimeException('Chat history contract failed.');
    $chat->unarchive();
    if ($chat->unmute()->isMuted || !$chat->unpin()) throw new RuntimeException('Chat unmute or unpin contract failed.');

    $session = new ContractRecordingSession();
    $client = Client::forSession($session);
    $session->results = [
        true, [], ['code' => 'invite'], true, ['isMuted' => true, 'muteExpiration' => -1], true,
        true, true, true, true, true, ['isMuted' => false, 'muteExpiration' => 0], true,
    ];
    if (!$client->archiveChat('chat@c.us')) throw new RuntimeException('Client archive contract failed.');
    if ($client->getGroupMembershipRequests('group@g.us') !== [] || $client->getInviteInfo('code')['code'] !== 'invite') {
        throw new RuntimeException('Client group lookup contracts failed.');
    }
    $client->markChatUnread('chat@c.us');
    if (!$client->muteChat('chat@c.us')->isMuted || !$client->pinChat('chat@c.us')) {
        throw new RuntimeException('Client mute or pin contracts failed.');
    }
    $client->sendPresenceAvailable();
    $client->sendPresenceUnavailable();
    if (!$client->sendSeen('chat@c.us') || !$client->syncHistory('chat@c.us') || !$client->unarchiveChat('chat@c.us')) {
        throw new RuntimeException('Client chat forwarding contracts failed.');
    }
    if ($client->unmuteChat('chat@c.us')->isMuted || !$client->unpinChat('chat@c.us')) {
        throw new RuntimeException('Client unmute or unpin contracts failed.');
    }

    $session = new ContractRecordingSession();
    $channel = new Channel($session, [
        'id' => '120001@newsletter', 'name' => 'Contract channel', 'description' => 'Original',
        'isChannel' => true, 'isMuted' => false, 'lastMessage' => contractMessagePayload('channel-last'),
    ]);
    $matrix->assertProperties($channel, 'Channel');
    $session->results = [[], [], true, true, true, true, true];
    $session->contentResults = [$messageData('channel-send')];
    if ($channel->fetchMessages() !== [] || $channel->getSubscribers() !== [] || !$channel->mute()) {
        throw new RuntimeException('Channel collection or mute contracts failed.');
    }
    $sentChannelMessage = $channel->sendMessage('Channel message');
    if ($sentChannelMessage->id->id !== 'channel-send' || !$channel->sendSeen()
        || !$channel->setDescription('Updated') || !$channel->setSubject('Updated channel') || !$channel->unmute()) {
        throw new RuntimeException('Channel mutation contracts failed.');
    }

    $session = new ContractRecordingSession();
    $group = new GroupChat($session, [
        'id' => '120000@g.us', 'name' => 'Contract group', 'isGroup' => true,
        'groupMetadata' => [
            'owner' => ['_serialized' => '5511999999999@c.us', 'user' => '5511999999999', 'server' => 'c.us'],
            'creation' => 1_700_000_000,
            'desc' => 'Original group',
            'participants' => [[
                'id' => ['_serialized' => '5511999999999@c.us', 'user' => '5511999999999', 'server' => 'c.us'],
                'isAdmin' => true,
                'isSuperAdmin' => true,
            ]],
        ],
    ]);
    $matrix->assertProperties($group, 'GroupChat');
    $session->results = [[], 'invite-code', true, true, ['status' => 200], ['status' => 200]];
    if ($group->getGroupMembershipRequests() !== [] || $group->getInviteCode() !== 'invite-code'
        || !$group->setDescription('Updated group') || !$group->setSubject('Updated subject')
        || $group->promoteParticipants(['member@c.us'])->status !== 200
        || $group->demoteParticipants(['member@c.us'])->status !== 200
    ) {
        throw new RuntimeException('Group behavioral contracts failed.');
    }

    $session = new ContractRecordingSession();
    $contact = new \Pam\WhatsApp\Contact($session, $contactPayload('5511999999999@c.us'));
    if (!$contact->block()) throw new RuntimeException('Contact block contract failed.');
    if (!$contact->unblock()) throw new RuntimeException('Contact unblock contract failed.');
    if ($session->invocations !== [
        ['method' => 'blockContact', 'arguments' => ['5511999999999@c.us', true]],
        ['method' => 'blockContact', 'arguments' => ['5511999999999@c.us', false]],
    ]) throw new RuntimeException('Contact block round-trip forwarding failed.');

    $session = new ContractRecordingSession();
    $broadcastPayload = [
        'id' => ['_serialized' => 'broadcast@broadcast', 'user' => 'broadcast', 'server' => 'broadcast'],
        'timestamp' => 1_700_000_000, 'totalCount' => 2, 'unreadCount' => 1,
        'msgs' => [contractMessagePayload('broadcast-message')],
    ];
    $broadcast = new \Pam\WhatsApp\Broadcast($session, $broadcastPayload);
    $matrix->assertProperties($broadcast, 'Broadcast');
    $session->results = [
        ['id' => 'broadcast@broadcast', 'name' => 'Broadcast'],
        $contactPayload('broadcast@broadcast'),
        $broadcastPayload,
    ];
    $broadcastChat = $broadcast->getChat();
    $broadcastContact = $broadcast->getContact();
    $resolvedBroadcast = Client::forSession($session)->getBroadcastById('broadcast@broadcast');
    if ($broadcastChat->id->serialized !== 'broadcast@broadcast'
        || $broadcastContact->id->serialized !== 'broadcast@broadcast'
        || $resolvedBroadcast === null
        || $resolvedBroadcast->id->serialized !== 'broadcast@broadcast') {
        throw new RuntimeException('Broadcast hydration or relation contract failed.');
    }

    $session = new ContractRecordingSession();
    $client = Client::forSession($session);
    $notification = null;
    $client->on(EventType::GroupUpdated, static function (object $event) use (&$notification): void {
        $notification = $event;
    });
    $client->initialize();
    $session->emit(new BridgeEvent(EventType::GroupUpdated, [
        'id' => 'notification-id', 'author' => 'admin@c.us', 'body' => 'Updated',
        'chatId' => '120000@g.us', 'recipientIds' => ['member@c.us'], 'timestamp' => 1_700_000_000,
        'type' => GroupNotificationType::Subject->value,
    ]));
    if (!$notification instanceof \Pam\WhatsApp\Event\GroupNotification) {
        throw new RuntimeException('Group notification was not hydrated.');
    }
    $matrix->assertProperties($notification, 'GroupNotification');
    $session->results = [
        ['id' => '120000@g.us', 'name' => 'Group', 'isGroup' => true, 'groupMetadata' => ['creation' => 1_700_000_000]],
        $contactPayload('admin@c.us'),
        $contactPayload('member@c.us'),
    ];
    $session->contentResults = [$messageData('notification-reply')];
    $notificationChat = $notification->getChat();
    $notificationContact = $notification->getContact();
    $notificationRecipients = $notification->getRecipients();
    $notificationReply = $notification->reply('Reply');
    if ($notificationChat->id->serialized !== '120000@g.us'
        || $notificationContact->id->serialized !== 'admin@c.us'
        || count($notificationRecipients) !== 1
        || $notificationReply->id->id !== 'notification-reply') {
        throw new RuntimeException('Group notification relation or reply contract failed.');
    }

    $session = new ContractRecordingSession();
    $productPayload = [
        'id' => 'product-1', 'price' => '1990', 'thumbnailUrl' => 'https://example.test/product.png',
        'currency' => 'BRL', 'name' => 'Product', 'quantity' => 2,
    ];
    $order = new \Pam\WhatsApp\Order($session, [
        'products' => [$productPayload], 'subtotal' => '3980', 'total' => '3980',
        'currency' => 'BRL', 'createdAt' => 1_700_000_000,
    ]);
    $matrix->assertProperties($order, 'Order');
    $product = $order->products[0] ?? null;
    if (!$product instanceof \Pam\WhatsApp\Product) throw new RuntimeException('Order product was not hydrated.');
    $matrix->assertProperties($product, 'Product');
    $session->results = [[
        'id' => 'product-1', 'name' => 'Product', 'description' => 'Description', 'retailer_id' => 'sku-1',
    ]];
    if (!$product->getData() instanceof ProductMetadata) throw new RuntimeException('Product metadata was not hydrated.');

    $payment = new \Pam\WhatsApp\Payment([
        'id' => 'payment-1', 'paymentCurrency' => 'BRL', 'paymentAmount1000' => 1_990_000,
        'paymentMessageReceiverJid' => '5511999999999@c.us', 'paymentTransactionTimestamp' => 1_700_000_000,
        'paymentStatus' => 1, 'paymentTxnStatus' => 1, 'paymentNote' => 'Paid',
    ]);
    $matrix->assertProperties($payment, 'Payment');

    $pollMessage = new Message($session, $messageData('poll-message', MessageContentType::Poll));
    $session->results = [[[
        'voter' => 'member@c.us', 'selectedOptions' => [['id' => 1, 'name' => 'First']],
        'interractedAtTs' => 1_700_000_001,
        'parentMessage' => array_replace(contractMessagePayload('poll-parent'), [
            'contentType' => MessageContentType::Poll->value,
            'type' => MessageType::PollCreation->value,
        ]),
    ]]];
    $votes = $pollMessage->getPollVotes();
    $vote = $votes[0] ?? null;
    if (!$vote instanceof \Pam\WhatsApp\PollVote) throw new RuntimeException('Poll vote was not hydrated.');
    $matrix->assertProperties($vote, 'PollVote');
});

$payload = $report->payload('1.34.7', false);
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
$reportPath = getenv('PAM_WWEB_CONTRACT_CERTIFICATION_REPORT');
if (is_string($reportPath) && $reportPath !== '') {
    if (file_put_contents($reportPath, $json, LOCK_EX) !== strlen($json)) {
        throw new RuntimeException('Unable to write contract certification report.');
    }
}
fwrite(STDOUT, $json);
exit($report->failed() ? 1 : 0);
