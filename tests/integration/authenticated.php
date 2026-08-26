<?php

declare(strict_types=1);

use Pam\WhatsApp\BrowserSession;
use Pam\WhatsApp\Channel;
use Pam\WhatsApp\Chat;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\ClientState;
use Pam\WhatsApp\ConnectionState;
use Pam\WhatsApp\EventType;
use Pam\WhatsApp\GroupChat;
use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\RemoteAuth;
use Pam\WhatsApp\Auth\RemoteAuthOptions;
use Pam\WhatsApp\Auth\RemoteStore;
use Pam\WhatsApp\Auth\RemoteStoreOptions;
use Pam\WhatsApp\Message;
use Pam\WhatsApp\MessageMedia;
use Pam\WhatsApp\MessageSearchOptions;

require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Support/CertificationReport.php';

final class CertificationLocalAuth extends LocalAuth
{
    /** @var list<string> */
    public array $observedHooks = [];

    public function setup(Client $client): void
    {
        $this->observedHooks[] = 'setup';
        parent::setup($client);
    }

    public function beforeBrowserInitialized(): void
    {
        $this->observedHooks[] = 'beforeBrowserInitialized';
        parent::beforeBrowserInitialized();
    }

    public function afterBrowserInitialized(): void
    {
        $this->observedHooks[] = 'afterBrowserInitialized';
        parent::afterBrowserInitialized();
    }

    public function getAuthEventPayload(): mixed
    {
        $this->observedHooks[] = 'getAuthEventPayload';

        return parent::getAuthEventPayload();
    }

    public function afterAuthReady(): void
    {
        $this->observedHooks[] = 'afterAuthReady';
        parent::afterAuthReady();
    }

    public function destroy(): void
    {
        $this->observedHooks[] = 'destroy';
        parent::destroy();
    }
}

final class CertificationRemoteStore implements RemoteStore
{
    /** @var list<string> */
    public array $operations = [];

    public function __construct(private bool $exists = true) {}

    public function sessionExists(RemoteStoreOptions $options): bool
    {
        $this->operations[] = 'sessionExists:'.$options->session;

        return $this->exists;
    }

    public function extract(RemoteStoreOptions $options): void
    {
        if ($options->path === null || !is_dir($options->path)) {
            throw new RuntimeException('RemoteAuth extraction target was not prepared.');
        }
        $this->operations[] = 'extract:'.$options->session;
    }

    public function save(RemoteStoreOptions $options): void
    {
        if ($options->path === null || !is_dir($options->path)) {
            throw new RuntimeException('RemoteAuth backup source is unavailable.');
        }
        $this->operations[] = 'save:'.$options->session;
        $this->exists = true;
    }

    public function delete(RemoteStoreOptions $options): void
    {
        $this->operations[] = 'delete:'.$options->session;
        $this->exists = false;
    }
}

final class CertificationEventProbe
{
    /** @var array<int, list<object>> */
    private array $events = [];

    /** @param list<EventType> $types */
    public function attach(Client $client, array $types): void
    {
        foreach ($types as $type) {
            $client->on($type, function (object $event) use ($type): void {
                $this->events[$type->value][] = $event;
            });
        }
    }

    /** @param array<int, class-string> $expectedClasses */
    public function await(Client $client, array $expectedClasses, float $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $missing = [];
            foreach ($expectedClasses as $eventValue => $expectedClass) {
                $event = $this->events[$eventValue][0] ?? null;
                if (!$event instanceof $expectedClass) $missing[] = EventType::from($eventValue)->name;
            }
            if ($missing === []) return;
            $client->pump(min(0.5, max(0.0, $deadline - microtime(true))));
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Timed out awaiting typed events: '.implode(', ', $missing));
    }

    public function first(EventType $type): object
    {
        $event = $this->events[$type->value][0] ?? null;
        if (!is_object($event)) {
            throw new RuntimeException("No captured {$type->name} event is available.");
        }

        return $event;
    }

    public function has(EventType $type): bool
    {
        return isset($this->events[$type->value][0]);
    }

    /** @param list<EventType> $types */
    public function clear(array $types): void
    {
        foreach ($types as $type) unset($this->events[$type->value]);
    }
}

/** @return array<string, true> */
function certificationEntries(): array
{
    $payload = file_get_contents(dirname(__DIR__, 2).'/api-matrix.json');
    if (!is_string($payload)) throw new RuntimeException('Unable to read API matrix for certification.');
    $matrix = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($matrix)) throw new RuntimeException('API matrix is invalid.');
    $entries = [];
    $symbols = $matrix['symbols'] ?? null;
    if (!is_array($symbols)) throw new RuntimeException('API matrix symbols are invalid.');
    foreach ($symbols as $symbol) {
        if (!is_array($symbol) || !is_string($symbol['id'] ?? null)) continue;
        $entries['symbol:'.$symbol['id']] = true;
        $members = $symbol['members'] ?? [];
        if (!is_array($members)) continue;
        foreach ($members as $member) {
            if (!is_array($member) || !is_string($member['id'] ?? null)) continue;
            $entries['member:'.$symbol['id'].':'.$member['id']] = true;
        }
    }

    return $entries;
}

/** @return array{phpSymbol: class-string, properties: array<string, string>} */
function certificationPropertyContract(string $symbolId): array
{
    $payload = file_get_contents(dirname(__DIR__, 2).'/api-matrix.json');
    if (!is_string($payload)) throw new RuntimeException('Unable to read API matrix property contract.');
    $matrix = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($matrix) || !is_array($matrix['symbols'] ?? null)) {
        throw new RuntimeException('API matrix property contract is invalid.');
    }
    foreach ($matrix['symbols'] as $symbol) {
        if (!is_array($symbol) || ($symbol['id'] ?? null) !== $symbolId) continue;
        $phpSymbol = $symbol['phpSymbol'] ?? null;
        if (!is_string($phpSymbol) || !class_exists($phpSymbol)) {
            throw new RuntimeException("Mapped PHP symbol is unavailable: {$symbolId}");
        }
        $reflection = new ReflectionClass($phpSymbol);
        $properties = [];
        $members = $symbol['members'] ?? null;
        if (!is_array($members)) throw new RuntimeException("Matrix members are invalid: {$symbolId}");
        foreach ($members as $member) {
            if (!is_array($member)
                || ($member['kind'] ?? null) !== 2
                || !is_string($member['id'] ?? null)
                || !is_string($member['phpMember'] ?? null)
            ) continue;
            $phpMember = $member['phpMember'];
            if (!$reflection->hasProperty($phpMember) || !$reflection->getProperty($phpMember)->isPublic()) continue;
            $properties[$phpMember] = 'member:'.$symbolId.':'.$member['id'];
        }

        return ['phpSymbol' => $phpSymbol, 'properties' => $properties];
    }

    throw new RuntimeException("Unknown API matrix symbol: {$symbolId}");
}

/** @return list<string> */
function certificationPropertyCoverage(string $symbolId): array
{
    return array_values(certificationPropertyContract($symbolId)['properties']);
}

function assertCertificationProperties(object $value, string $symbolId): void
{
    $contract = certificationPropertyContract($symbolId);
    $phpSymbol = $contract['phpSymbol'];
    if (!$value instanceof $phpSymbol) {
        throw new RuntimeException("Hydrated value does not implement {$phpSymbol} for {$symbolId}.");
    }
    $reflection = new ReflectionObject($value);
    $observedTypes = [];
    foreach ($contract['properties'] as $phpProperty => $entry) {
        if (!$reflection->hasProperty($phpProperty)) {
            throw new RuntimeException("Hydrated value lacks {$entry}.");
        }
        $property = $reflection->getProperty($phpProperty);
        if (!$property->isPublic() || !$property->isInitialized($value)) {
            throw new RuntimeException("Hydrated property is not publicly initialized: {$entry}.");
        }
        $observedTypes[$entry] = get_debug_type($property->getValue($value));
    }
    if (count($observedTypes) !== count($contract['properties'])) {
        throw new RuntimeException("Hydrated property audit was incomplete for {$symbolId}.");
    }
}

/** @param list<string> $errors */
function attemptCertificationRestore(string $field, Closure $restore, array &$errors): void
{
    try {
        if ($restore() !== true) $errors[] = $field;
    } catch (Throwable $exception) {
        $errors[] = $field.' ('.$exception::class.')';
    }
}

/** @param Closure(Chat): bool $matches */
function awaitCertificationChat(Client $client, string $chatId, Closure $matches, float $timeoutSeconds = 5.0): Chat
{
    $deadline = microtime(true) + $timeoutSeconds;
    do {
        $chat = $client->getChatById($chatId);
        if ($chat instanceof Chat && $matches($chat)) return $chat;
        $client->pump(min(0.25, max(0.0, $deadline - microtime(true))));
    } while (microtime(true) < $deadline);

    throw new RuntimeException('Timed out awaiting the expected chat state.');
}

function removeCertificationDirectory(string $directory): void
{
    $expectedPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR.'pam-wweb-remote-certification-';
    if (!str_starts_with($directory, $expectedPrefix) || !is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) continue;
        $path = $entry->getPathname();
        if ($entry->isDir() && !$entry->isLink()) {
            if (!rmdir($path)) throw new RuntimeException("Unable to remove certification directory: {$path}");
        } elseif (!unlink($path)) {
            throw new RuntimeException("Unable to remove certification file: {$path}");
        }
    }
    if (!rmdir($directory)) throw new RuntimeException("Unable to remove certification root: {$directory}");
}

/** @return non-empty-string|null */
function environmentId(string $name): ?string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') return null;
    if (preg_match('/^[A-Za-z0-9._:-]+@[A-Za-z0-9._-]+$/', $value) !== 1) {
        throw new InvalidArgumentException("{$name} must be a complete WhatsApp id.");
    }

    return $value;
}

$authPath = getenv('PAM_WWEB_AUTH_PATH');
if (!is_string($authPath) || $authPath === '') {
    throw new RuntimeException('PAM_WWEB_AUTH_PATH must point to a dedicated authenticated test profile root.');
}
$allowMutations = getenv('PAM_WWEB_ALLOW_MUTATIONS') === '1';
$certifyEvents = getenv('PAM_WWEB_CERTIFY_EVENTS') === '1';
$certifyInbound = getenv('PAM_WWEB_CERTIFY_INBOUND') === '1';
$certifyMedia = getenv('PAM_WWEB_CERTIFY_MEDIA') === '1';
$certifyGroupMutations = getenv('PAM_WWEB_CERTIFY_GROUP_MUTATIONS') === '1';
$certifyChannelMutations = getenv('PAM_WWEB_CERTIFY_CHANNEL_MUTATIONS') === '1';
$certifyChannelPosts = getenv('PAM_WWEB_CERTIFY_CHANNEL_POSTS') === '1';
$certifyChatMutations = getenv('PAM_WWEB_CERTIFY_CHAT_MUTATIONS') === '1';
$certifyContactMutations = getenv('PAM_WWEB_CERTIFY_CONTACT_MUTATIONS') === '1';
$testChatId = environmentId('PAM_WWEB_TEST_CHAT_ID');
$testChatIndexValue = getenv('PAM_WWEB_TEST_CHAT_INDEX');
$testChatIndex = is_string($testChatIndexValue) && $testChatIndexValue !== ''
    ? filter_var($testChatIndexValue, FILTER_VALIDATE_INT)
    : null;
$testChatExpectedNameValue = getenv('PAM_WWEB_TEST_CHAT_EXPECTED_NAME');
$testChatExpectedName = is_string($testChatExpectedNameValue) && $testChatExpectedNameValue !== ''
    ? $testChatExpectedNameValue
    : null;
if (($testChatIndex === null) !== ($testChatExpectedName === null)
    || ($testChatIndex !== null && ($testChatIndex < 1 || $testChatIndex > 10_000))
) {
    throw new InvalidArgumentException(
        'PAM_WWEB_TEST_CHAT_INDEX (1-based) and PAM_WWEB_TEST_CHAT_EXPECTED_NAME must be provided together.',
    );
}
$testGroupId = environmentId('PAM_WWEB_TEST_GROUP_ID');
$testGroupParticipantId = environmentId('PAM_WWEB_TEST_GROUP_PARTICIPANT_ID');
$testContactId = environmentId('PAM_WWEB_TEST_CONTACT_ID');
$testChannelId = environmentId('PAM_WWEB_TEST_CHANNEL_ID');
$report = new CertificationReport(certificationEntries());
$report->check('auth.remote-contract', array_merge([
    'member:RemoteAuth:1:constructor',
    'member:AuthStrategy:1:logout',
    'member:AuthStrategy:1:disconnect',
    'member:Store:1:sessionExists',
    'member:Store:1:extract',
    'member:Store:1:save',
    'member:Store:1:delete',
], certificationPropertyCoverage('RemoteAuth')), static function (): void {
    $temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR.'pam-wweb-remote-certification-'.bin2hex(random_bytes(12));
    if (!mkdir($temporaryRoot, 0700)) {
        throw new RuntimeException('Unable to create the RemoteAuth certification root.');
    }
    $clock = 1_000.0;
    $store = new CertificationRemoteStore();
    $auth = new RemoteAuth(new RemoteAuthOptions(
        store: $store,
        backupSyncIntervalMs: 60_000,
        clientId: 'certification',
        dataPath: $temporaryRoot,
        clock: static function () use (&$clock): float {
            return $clock;
        },
    ));
    try {
        assertCertificationProperties($auth, 'RemoteAuth');
        $profile = $auth->prepare();
        $auth->afterAuthReady();
        $clock += 60.0;
        $auth->onPump($profile);
        $auth->logout();
        $auth->disconnect();
        foreach (['sessionExists:', 'extract:', 'save:', 'delete:'] as $operation) {
            if (!array_any($store->operations, static fn (string $observed): bool => str_starts_with($observed, $operation))) {
                throw new RuntimeException("Remote store operation was not observed: {$operation}");
            }
        }
    } finally {
        removeCertificationDirectory($temporaryRoot);
    }
});
$eventProbe = new CertificationEventProbe();
$authStrategy = new CertificationLocalAuth(new \Pam\WhatsApp\Auth\LocalAuthOptions('certification', $authPath));
$client = Client::launch(new ClientOptions(
    authStrategy: $authStrategy,
    headless: true,
    browserTimeoutSeconds: 60.0,
    authenticationTimeoutSeconds: 90.0,
    browserArguments: ['--disable-dev-shm-usage'],
));
$certifiedEventTypes = [
    EventType::MessageAcknowledged,
    EventType::MessageCreated,
    EventType::MessageEdited,
    EventType::MessageReaction,
    EventType::MessageRevokedEveryone,
];
$eventProbe->attach($client, [
    EventType::Authenticated,
    EventType::Ready,
    EventType::StateChanged,
    EventType::LoadingScreen,
]);
if ($certifyEvents) $eventProbe->attach($client, $certifiedEventTypes);
if ($certifyInbound) $eventProbe->attach($client, [EventType::MessageReceived]);
if ($certifyMedia) $eventProbe->attach($client, [EventType::MediaUploaded]);
if ($certifyGroupMutations) $eventProbe->attach($client, [EventType::GroupUpdated]);
if ($certifyChatMutations) $eventProbe->attach($client, [EventType::ChatArchived]);
$webVersion = '';

try {
    $client->initialize();
    $deadline = microtime(true) + 120.0;
    while ($client->state !== ClientState::Ready && microtime(true) < $deadline) {
        if (in_array($client->state, [ClientState::Closed, ClientState::Failed], true)) break;
        $client->pump(1.0);
    }
    if ($client->state !== ClientState::Ready) {
        throw new RuntimeException(
            'Authenticated profile is not ready. Run the QR smoke with the same LocalAuth profile and scan it first.',
        );
    }

    $report->check('client.lifecycle-events', array_merge([
        'member:Client:1:constructor',
        'member:Client:1:initialize',
        'member:Client:1:on',
        'member:Client:3:authenticated',
        'member:Client:3:ready',
        'member:Events:4:AUTHENTICATED',
        'member:Events:4:READY',
        'member:LocalAuth:1:constructor',
        'member:AuthStrategy:1:setup',
        'member:AuthStrategy:1:beforeBrowserInitialized',
        'member:AuthStrategy:1:afterBrowserInitialized',
        'member:AuthStrategy:1:getAuthEventPayload',
        'member:AuthStrategy:1:afterAuthReady',
    ], certificationPropertyCoverage('LocalAuth')), static function () use ($client, $eventProbe, $authStrategy): void {
        $eventProbe->await($client, [
            EventType::Authenticated->value => \Pam\WhatsApp\Event\Authenticated::class,
            EventType::Ready->value => \Pam\WhatsApp\Event\Ready::class,
        ], 1.0);
        assertCertificationProperties($authStrategy, 'LocalAuth');
        foreach ([
            'setup',
            'beforeBrowserInitialized',
            'afterBrowserInitialized',
            'getAuthEventPayload',
            'afterAuthReady',
        ] as $hook) {
            if (!in_array($hook, $authStrategy->observedHooks, true)) {
                throw new RuntimeException("Authentication hook was not observed: {$hook}.");
            }
        }
    });

    if (!$certifyInbound) {
        $report->skip('message.received-event', 'Set PAM_WWEB_CERTIFY_INBOUND=1 and send an inbound message while the certification is running.');
    } else {
        $report->check('message.received-event', array_merge([
            'member:Client:3:message',
            'member:Events:4:MESSAGE_RECEIVED',
        ], certificationPropertyCoverage('Message'), certificationPropertyCoverage('MessageId')), static function () use ($client, $eventProbe): void {
            $eventProbe->await($client, [
                EventType::MessageReceived->value => \Pam\WhatsApp\Event\MessageReceived::class,
            ], 120.0);
            $event = $eventProbe->first(EventType::MessageReceived);
            if (!$event instanceof \Pam\WhatsApp\Event\MessageReceived || $event->message->fromMe) {
                throw new RuntimeException('Inbound message event was not hydrated with a received Message.');
            }
            assertCertificationProperties($event->message, 'Message');
            assertCertificationProperties($event->message->id, 'MessageId');
        });
    }

    if ($eventProbe->has(EventType::StateChanged)) {
        $report->check('client.state-event', [
            'member:Client:3:change_state',
            'member:Events:4:STATE_CHANGED',
        ], static function () use ($eventProbe): void {
            if (!$eventProbe->first(EventType::StateChanged) instanceof \Pam\WhatsApp\Event\ConnectionStateChanged) {
                throw new RuntimeException('State change event was not hydrated to its typed payload.');
            }
        });
    } else {
        $report->skip('client.state-event', 'The authenticated startup did not emit a state change event.');
    }

    if ($eventProbe->has(EventType::LoadingScreen)) {
        $report->check('client.loading-event', [
            'member:Client:3:loading_screen',
            'member:Events:4:LOADING_SCREEN',
        ], static function () use ($eventProbe): void {
            if (!$eventProbe->first(EventType::LoadingScreen) instanceof \Pam\WhatsApp\Event\LoadingScreen) {
                throw new RuntimeException('Loading event was not hydrated to its typed payload.');
            }
        });
    } else {
        $report->skip('client.loading-event', 'The authenticated startup did not emit a loading screen event.');
    }

    $report->check('client.connection', array_merge([
        'member:Client:1:getWWebVersion',
        'member:Client:1:getState',
    ], certificationPropertyCoverage('Client')), function () use ($client, &$webVersion): void {
        $webVersion = $client->getWWebVersion();
        if ($webVersion === ''
            || $client->getState() !== ConnectionState::Connected
            || $client->info === null
            || $client->interface === null
            || $client->pupBrowser === null
            || $client->pupPage === null
        ) {
            throw new RuntimeException('Connected client metadata is incomplete.');
        }
        assertCertificationProperties($client, 'Client');
    });
    $labels = [];
    $broadcasts = [];
    $contacts = [];
    $chats = [];
    $report->check('client.collections', [
        'member:Client:1:getChats',
        'member:Client:1:getChannels',
        'member:Client:1:getContacts',
        'member:Client:1:getBlockedContacts',
        'member:Client:1:getBroadcasts',
        'member:Client:1:getLabels',
    ], static function () use ($client, &$labels, &$broadcasts, &$contacts, &$chats): void {
        $chats = $client->getChats();
        $client->getChannels();
        $contacts = $client->getContacts();
        $client->getBlockedContacts();
        $broadcasts = $client->getBroadcasts();
        $labels = $client->getLabels();
    });
    if ($testChatId === null && $testChatIndex !== null && $testChatExpectedName !== null) {
        $candidate = $chats[$testChatIndex - 1] ?? null;
        if (!$candidate instanceof \Pam\WhatsApp\PrivateChat
            || !hash_equals($testChatExpectedName, $candidate->name)
        ) {
            $matchingChats = array_values(array_filter(
                $chats,
                static fn (object $chat): bool => $chat instanceof \Pam\WhatsApp\PrivateChat
                    && hash_equals($testChatExpectedName, $chat->name),
            ));
            if (count($matchingChats) !== 1) {
                throw new RuntimeException(sprintf(
                    'Indexed target did not match and exact-name lookup found %d private chats; no mutations were executed.',
                    count($matchingChats),
                ));
            }
            $candidate = $matchingChats[0];
        }
        $testChatId = $candidate->id->serialized;
    }
    if ($allowMutations && $testChatId !== null) {
        $cleanupChat = $client->getChatById($testChatId);
        if (!$cleanupChat instanceof Chat || $cleanupChat->isGroup) {
            throw new RuntimeException('Certification cleanup target is not a private chat.');
        }
        foreach ($cleanupChat->fetchMessages(new MessageSearchOptions(limit: 50)) as $artifact) {
            $filename = is_string($artifact->rawData['filename'] ?? null)
                ? $artifact->rawData['filename']
                : null;
            if ($artifact->fromMe
                && $artifact->type !== \Pam\WhatsApp\MessageType::Revoked
                && (str_starts_with($artifact->body, 'PAM certification')
                    || $filename === 'pam-certification.png')
            ) {
                $artifact->delete(everyone: true, clearMedia: true);
            }
        }
        $client->pump(0.5);
        $eventProbe->clear($certifiedEventTypes);
        if ($certifyMedia) $eventProbe->clear([EventType::MediaUploaded]);
    }
    $report->check('client.identity', array_merge([
        'member:Client:1:getContactById',
        'member:Client:1:getContactDeviceCount',
        'member:Client:1:getContactLidAndPhone',
        'member:Client:1:getCommonGroups',
        'member:Client:1:getCountryCode',
        'member:Client:1:getFormattedNumber',
        'member:Client:1:getNumberId',
        'member:Client:1:getProfilePicUrl',
        'member:Client:1:isRegisteredUser',
        'member:Contact:1:getProfilePicUrl',
        'member:Contact:1:getAbout',
        'member:Contact:1:getChat',
        'member:Contact:1:getCommonGroups',
        'member:Contact:1:getCountryCode',
        'member:Contact:1:getFormattedNumber',
        'member:Contact:1:getBroadcast',
        'member:ClientInfo:1:getBatteryStatus',
    ], certificationPropertyCoverage('ClientInfo'), certificationPropertyCoverage('Contact'), certificationPropertyCoverage('ContactId')), static function () use ($client): void {
        $info = $client->info ?? throw new RuntimeException('Client info is unavailable.');
        $id = $info->wid->serialized;
        $step = 'getContactById';
        try {
            $contact = $client->getContactById($id);
            $step = 'property audit';
            assertCertificationProperties($info, 'ClientInfo');
            assertCertificationProperties($contact, 'Contact');
            assertCertificationProperties($info->wid, 'ContactId');
            assertCertificationProperties($contact->id, 'ContactId');
            $step = 'Contact::getProfilePicUrl';
            $contact->getProfilePicUrl();
            $step = 'Contact::getAbout';
            $contact->getAbout();
            $step = 'Contact::getChat';
            $contact->getChat();
            $step = 'Contact::getCommonGroups';
            $contact->getCommonGroups();
            $step = 'Contact::getCountryCode';
            if ($contact->getCountryCode() === '') throw new RuntimeException('Contact country code is empty.');
            $step = 'Contact::getFormattedNumber';
            if ($contact->getFormattedNumber() === '') throw new RuntimeException('Contact formatted number is empty.');
            $step = 'Contact::getBroadcast';
            $contact->getBroadcast();
            $step = 'Client::getContactDeviceCount';
            $client->getContactDeviceCount($id);
            $step = 'Client::getContactLidAndPhone';
            $client->getContactLidAndPhone([$id]);
            $step = 'Client::getCommonGroups';
            $client->getCommonGroups($id);
            $step = 'Client::getCountryCode';
            if ($client->getCountryCode($info->wid->user) === '') throw new RuntimeException('Country code is empty.');
            $step = 'Client::getFormattedNumber';
            if ($client->getFormattedNumber($info->wid->user) === '') throw new RuntimeException('Formatted number is empty.');
            $step = 'Client::getNumberId';
            $numberId = $client->getNumberId($info->wid->user);
            if (!$numberId instanceof \Pam\WhatsApp\ContactId) throw new RuntimeException('Current account is not registered.');
            $step = 'Client::getProfilePicUrl';
            $client->getProfilePicUrl($id);
            $step = 'Client::isRegisteredUser';
            if (!$client->isRegisteredUser($info->wid->user)) throw new RuntimeException('Current account was not recognized.');
            $step = 'ClientInfo::getBatteryStatus';
            $info->getBatteryStatus();
        } catch (Throwable $exception) {
            throw new RuntimeException("Identity step {$step} failed: {$exception->getMessage()}", previous: $exception);
        }
    });

    $businessContact = null;
    foreach ($contacts as $candidate) {
        if ($candidate instanceof \Pam\WhatsApp\BusinessContact) {
            $businessContact = $candidate;
            break;
        }
    }
    if (!$businessContact instanceof \Pam\WhatsApp\BusinessContact) {
        $report->skip('contact.business-shape', 'The authenticated account has no hydrated business contact.');
    } else {
        $businessCoverage = certificationPropertyCoverage('BusinessContact');
        if ($businessContact->businessProfile->categories !== []) {
            $businessCoverage = array_merge($businessCoverage, certificationPropertyCoverage('BusinessCategory'));
        }
        if ($businessContact->businessProfile->businessHours instanceof \Pam\WhatsApp\BusinessHours) {
            $businessCoverage = array_merge(
                $businessCoverage,
                certificationPropertyCoverage('BusinessHours'),
                certificationPropertyCoverage('BusinessHoursOfDay'),
            );
        }
        $report->check('contact.business-shape', $businessCoverage, static function () use ($businessContact): void {
            assertCertificationProperties($businessContact, 'BusinessContact');
            assertCertificationProperties($businessContact, 'Contact');
            assertCertificationProperties($businessContact->businessProfile->id, 'ContactId');
            foreach ($businessContact->businessProfile->categories as $category) {
                assertCertificationProperties($category, 'BusinessCategory');
            }
            $hours = $businessContact->businessProfile->businessHours;
            if ($hours instanceof \Pam\WhatsApp\BusinessHours) {
                assertCertificationProperties($hours, 'BusinessHours');
                foreach ($hours->config as $day) {
                    assertCertificationProperties($day, 'BusinessHoursOfDay');
                }
            }
        });
    }

    if (!$certifyContactMutations) {
        $report->skip('contact.reversible-block', 'Set PAM_WWEB_CERTIFY_CONTACT_MUTATIONS=1 for a dedicated contact.');
    } elseif (!$allowMutations || $testContactId === null) {
        $report->skip('contact.reversible-block', 'Contact mutation certification requires PAM_WWEB_TEST_CONTACT_ID.');
    } else {
        $report->check('contact.reversible-block', [
            'member:Contact:1:block',
            'member:Contact:1:unblock',
        ], static function () use ($client, $testContactId): void {
            $contact = $client->getContactById($testContactId);
            if ($contact->isMe || $contact->isGroup) {
                throw new RuntimeException('Configured contact mutation target must be another private contact.');
            }
            $originalBlocked = $contact->isBlocked;
            try {
                if ($originalBlocked) {
                    if (!$contact->unblock() || !$contact->block()) {
                        throw new RuntimeException('Contact block variants did not round-trip.');
                    }
                } elseif (!$contact->block() || !$contact->unblock()) {
                    throw new RuntimeException('Contact block variants did not round-trip.');
                }
            } finally {
                $current = $client->getContactById($testContactId);
                if ($current->isBlocked !== $originalBlocked) {
                    $restored = $originalBlocked ? $current->block() : $current->unblock();
                    if (!$restored) throw new RuntimeException('Unable to restore contact block state.');
                }
            }
        });
    }

    if ($labels === []) {
        $report->skip('labels.read', 'The authenticated account has no labels to certify.');
    } else {
        $report->check('labels.read', array_merge([
            'member:Client:1:getLabelById',
            'member:Client:1:getChatsByLabelId',
            'member:Label:1:getChats',
        ], certificationPropertyCoverage('Label')), static function () use ($client, $labels): void {
            $label = $labels[0];
            assertCertificationProperties($label, 'Label');
            $resolved = $client->getLabelById($label->id);
            assertCertificationProperties($resolved, 'Label');
            $client->getChatsByLabelId($label->id);
            $label->getChats();
        });
    }

    if ($broadcasts === []) {
        $report->skip('broadcast.read', 'The authenticated account has no broadcasts to certify.');
    } else {
        $report->check('broadcast.read', array_merge([
            'member:Client:1:getBroadcastById',
            'member:Broadcast:1:getChat',
            'member:Broadcast:1:getContact',
        ], certificationPropertyCoverage('Broadcast')), static function () use ($client, $broadcasts): void {
            $broadcast = $broadcasts[0];
            assertCertificationProperties($broadcast, 'Broadcast');
            $resolved = $client->getBroadcastById($broadcast->id->serialized);
            if (!$resolved instanceof \Pam\WhatsApp\Broadcast) throw new RuntimeException('Broadcast lookup returned null.');
            assertCertificationProperties($resolved, 'Broadcast');
            $broadcast->getChat();
            $broadcast->getContact();
        });
    }
    $report->check('client.search', [
        'member:Client:1:searchMessages',
        'member:Client:1:searchChannels',
    ], static function () use ($client): void {
        $client->searchMessages('pam-certification-query-that-should-not-exist');
        $client->searchChannels();
    });

    $readOnlyChat = $testChatId === null
        ? array_find(
            $chats,
            static fn (object $chat): bool => $chat instanceof \Pam\WhatsApp\PrivateChat
                && $chat->lastMessage instanceof Message,
        )
        : null;
    $readOnlyChatId = $testChatId ?? ($readOnlyChat instanceof Chat ? $readOnlyChat->id->serialized : null);
    if ($readOnlyChatId === null) {
        $report->skip('chat.read', 'No explicit or automatically discoverable private chat is available.');
    } else {
        $fetchedMessages = [];
        $selectedChat = null;
        $report->check('chat.read', array_merge([
            'member:Client:1:getChatById',
            'member:Chat:1:fetchMessages',
            'member:Chat:1:getContact',
        ], certificationPropertyCoverage('Chat')), static function () use ($client, $readOnlyChatId, &$fetchedMessages, &$selectedChat): void {
            $chat = $client->getChatById($readOnlyChatId);
            if (!$chat instanceof Chat) throw new RuntimeException('Configured chat did not hydrate as Chat.');
            $selectedChat = $chat;
            assertCertificationProperties($chat, 'Chat');
            $fetchedMessages = $chat->fetchMessages(new MessageSearchOptions(limit: 3));
            $chat->getContact();
        });
        if (!$selectedChat instanceof Chat) {
            $report->skip('chat.labels', 'The selected chat did not hydrate.');
            $report->skip('chat.pinned-messages', 'The selected chat did not hydrate.');
            $report->skip('chat.customer-note', 'The selected chat did not hydrate.');
        } else {
            $report->check('chat.labels', [
                'member:Chat:1:getLabels',
                'member:Client:1:getChatLabels',
            ], static function () use ($client, $readOnlyChatId, $selectedChat): void {
                $selectedChat->getLabels();
                $client->getChatLabels($readOnlyChatId);
            });
            $report->check('chat.pinned-messages', [
                'member:Chat:1:getPinnedMessages',
                'member:Client:1:getPinnedMessages',
            ], static function () use ($client, $readOnlyChatId, $selectedChat): void {
                $selectedChat->getPinnedMessages();
                $client->getPinnedMessages($readOnlyChatId);
            });
            $report->check('chat.customer-note', [
                'member:Chat:1:getCustomerNote',
                'member:Client:1:getCustomerNote',
            ], static function () use ($client, $readOnlyChatId, $selectedChat): void {
                $selectedChat->getCustomerNote();
                $client->getCustomerNote($readOnlyChatId);
            });
        }
        if ($fetchedMessages === []) {
            $report->skip('message.read', 'The configured chat has no existing messages to certify.');
        } else {
            $existingMessage = $fetchedMessages[0];
            $withoutMedia = !$existingMessage->hasMedia;
            $messageOrder = null;
            $messagePayment = null;
            $messageReadCoverage = array_merge([
                'member:Message:1:getChat',
                'member:Message:1:getContact',
                'member:Message:1:getGroupMentions',
                'member:Message:1:getInfo',
                'member:Message:1:getMentions',
                'member:Message:1:getOrder',
                'member:Message:1:getPayment',
                'member:Message:1:getQuotedMessage',
                'member:Message:1:getReactions',
                'member:Message:1:reload',
            ], certificationPropertyCoverage('Message'), certificationPropertyCoverage('MessageId'));
            if ($withoutMedia) $messageReadCoverage[] = 'member:Message:1:downloadMedia';
            $report->check('message.read', $messageReadCoverage, static function () use ($existingMessage, $withoutMedia, &$messageOrder, &$messagePayment): void {
                assertCertificationProperties($existingMessage, 'Message');
                assertCertificationProperties($existingMessage->id, 'MessageId');
                $existingMessage->getChat();
                $existingMessage->getContact();
                $existingMessage->getGroupMentions();
                $existingMessage->getInfo();
                $existingMessage->getMentions();
                $messageOrder = $existingMessage->getOrder();
                $messagePayment = $existingMessage->getPayment();
                $quoted = $existingMessage->getQuotedMessage();
                if ($quoted instanceof Message) {
                    assertCertificationProperties($quoted, 'Message');
                    assertCertificationProperties($quoted->id, 'MessageId');
                }
                $existingMessage->getReactions();
                $reloaded = $existingMessage->reload();
                if ($reloaded instanceof Message) {
                    assertCertificationProperties($reloaded, 'Message');
                    assertCertificationProperties($reloaded->id, 'MessageId');
                }
                if ($withoutMedia && $existingMessage->downloadMedia() !== null) {
                    throw new RuntimeException('A message without media returned downloadable media.');
                }
            });

            if (!$messageOrder instanceof \Pam\WhatsApp\Order) {
                $report->skip('message.order-shape', 'The selected message is not an order.');
            } else {
                $orderCoverage = certificationPropertyCoverage('Order');
                if ($messageOrder->products !== []) {
                    $orderCoverage = array_merge($orderCoverage, [
                        'member:Product:1:getData',
                    ], certificationPropertyCoverage('Product'));
                }
                $report->check('message.order-shape', $orderCoverage, static function () use ($messageOrder): void {
                    assertCertificationProperties($messageOrder, 'Order');
                    foreach ($messageOrder->products as $product) {
                        assertCertificationProperties($product, 'Product');
                        $product->getData();
                    }
                });
            }

            if (!$messagePayment instanceof \Pam\WhatsApp\Payment) {
                $report->skip('message.payment-shape', 'The selected message is not a payment.');
            } else {
                $report->check(
                    'message.payment-shape',
                    certificationPropertyCoverage('Payment'),
                    static function () use ($messagePayment): void {
                        assertCertificationProperties($messagePayment, 'Payment');
                    },
                );
            }

            $pollMessage = null;
            foreach ($fetchedMessages as $candidate) {
                if ($candidate->contentType === \Pam\WhatsApp\MessageContentType::Poll) {
                    $pollMessage = $candidate;
                    break;
                }
            }
            if (!$pollMessage instanceof Message) {
                $report->skip('message.poll-votes', 'No poll message was found in the fetched window.');
            } else {
                $pollVotes = [];
                $report->check('message.poll-votes', [
                    'member:Message:1:getPollVotes',
                ], static function () use ($pollMessage, &$pollVotes): void {
                    $pollVotes = $pollMessage->getPollVotes();
                });
                if ($pollVotes === []) {
                    $report->skip('message.poll-vote-shapes', 'The selected poll has no votes.');
                } else {
                    $report->check(
                        'message.poll-vote-shapes',
                        certificationPropertyCoverage('PollVote'),
                        static function () use ($pollVotes): void {
                            foreach ($pollVotes as $vote) assertCertificationProperties($vote, 'PollVote');
                        },
                    );
                }
            }
        }
    }

    if (!$certifyChatMutations) {
        $report->skip('chat.reversible-mutations', 'Set PAM_WWEB_CERTIFY_CHAT_MUTATIONS=1 for a dedicated private chat.');
    } elseif (!$allowMutations || $testChatId === null) {
        $report->skip('chat.reversible-mutations', 'Chat mutation certification requires mutations and PAM_WWEB_TEST_CHAT_ID.');
    } else {
        $report->check('chat.reversible-mutations', [
            'member:Client:1:getChatById',
            'member:Chat:1:archive',
            'member:Chat:1:unarchive',
            'member:Chat:1:pin',
            'member:Chat:1:unpin',
            'member:Chat:1:mute',
            'member:Chat:1:unmute',
            'member:Chat:1:sendStateRecording',
            'member:Chat:1:syncHistory',
            'member:Client:3:chat_archived',
        ], static function () use ($client, $eventProbe, $testChatId): void {
            $chat = $client->getChatById($testChatId);
            if (!$chat instanceof Chat || $chat->isGroup) {
                throw new RuntimeException('Configured mutation target is not a private chat.');
            }
            $originalArchived = $chat->archived;
            $originalPinned = $chat->pinned;
            $originalMuted = $chat->isMuted;
            $originalMuteExpiration = $chat->muteExpiration;
            $archiveChanged = false;
            $pinChanged = false;
            $muteChanged = false;
            try {
                if ($originalArchived) $chat->unarchive(); else $chat->archive();
                $archiveChanged = true;
                awaitCertificationChat(
                    $client,
                    $testChatId,
                    static fn (Chat $value): bool => $value->archived !== $originalArchived,
                );

                $pinChanged = $originalPinned ? $chat->unpin() === false : $chat->pin();
                if (!$pinChanged) throw new RuntimeException('Chat pin state could not be changed.');
                awaitCertificationChat(
                    $client,
                    $testChatId,
                    static fn (Chat $value): bool => $value->pinned !== $originalPinned,
                );

                $muteResult = $originalMuted ? $chat->unmute() : $chat->mute(new DateTimeImmutable('+5 minutes'));
                $muteChanged = $muteResult->isMuted !== $originalMuted;
                if (!$muteChanged || $chat->isMuted === $originalMuted) {
                    throw new RuntimeException('Chat mute state did not change.');
                }
                $chat->sendStateRecording();
                if (!$chat->clearState()) throw new RuntimeException('Unable to clear recording state.');
                if (!$chat->syncHistory()) throw new RuntimeException('Unable to synchronize chat history.');
                $eventProbe->await($client, [
                    EventType::ChatArchived->value => \Pam\WhatsApp\Event\ChatArchiveChanged::class,
                ], 30.0);
            } finally {
                $restoreErrors = [];
                if ($muteChanged) {
                    attemptCertificationRestore('mute', static function () use ($chat, $originalMuted, $originalMuteExpiration): bool {
                        if (!$originalMuted) return !$chat->unmute()->isMuted;
                        $until = $originalMuteExpiration > time()
                            ? new DateTimeImmutable('@'.$originalMuteExpiration)
                            : null;

                        return $chat->mute($until)->isMuted;
                    }, $restoreErrors);
                }
                if ($pinChanged) {
                    attemptCertificationRestore(
                        'pin',
                        static fn (): bool => ($originalPinned ? $chat->pin() : $chat->unpin()) === $originalPinned,
                        $restoreErrors,
                    );
                }
                if ($archiveChanged) {
                    attemptCertificationRestore('archive', static function () use ($chat, $originalArchived): bool {
                        if ($originalArchived) $chat->archive(); else $chat->unarchive();

                        return true;
                    }, $restoreErrors);
                }
                if ($restoreErrors !== []) {
                    throw new RuntimeException('Unable to restore original chat fields: '.implode(', ', $restoreErrors).'.');
                }
            }
        });

        $report->check('client.chat-reversible-variants', [
            'member:Client:1:archiveChat',
            'member:Client:1:unarchiveChat',
            'member:Client:1:pinChat',
            'member:Client:1:unpinChat',
            'member:Client:1:muteChat',
            'member:Client:1:unmuteChat',
            'member:Client:1:markChatUnread',
            'member:Client:1:sendSeen',
            'member:Client:1:syncHistory',
            'member:Client:1:sendPresenceAvailable',
            'member:Client:1:sendPresenceUnavailable',
            'member:Chat:1:markUnread',
        ], static function () use ($client, $testChatId): void {
            $chat = $client->getChatById($testChatId);
            if (!$chat instanceof Chat || $chat->isGroup) {
                throw new RuntimeException('Configured client-variant target is not a private chat.');
            }
            $originalArchived = $chat->archived;
            $originalPinned = $chat->pinned;
            $originalMuted = $chat->isMuted;
            $originalMuteExpiration = $chat->muteExpiration;
            try {
                if ($originalArchived) {
                    if ($client->unarchiveChat($testChatId) !== false) {
                        throw new RuntimeException('Client archive variants did not round-trip.');
                    }
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => !$value->archived);
                    if (!$client->archiveChat($testChatId)) throw new RuntimeException('Client archive variants did not round-trip.');
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => $value->archived);
                } else {
                    if (!$client->archiveChat($testChatId)) throw new RuntimeException('Client archive variants did not round-trip.');
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => $value->archived);
                    if ($client->unarchiveChat($testChatId) !== false) {
                        throw new RuntimeException('Client archive variants did not round-trip.');
                    }
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => !$value->archived);
                }

                if ($originalPinned) {
                    if ($client->unpinChat($testChatId) !== false) {
                        throw new RuntimeException('Client pin variants did not round-trip.');
                    }
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => !$value->pinned);
                    if (!$client->pinChat($testChatId)) throw new RuntimeException('Client pin variants did not round-trip.');
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => $value->pinned);
                } else {
                    if (!$client->pinChat($testChatId)) throw new RuntimeException('Client pin variants did not round-trip.');
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => $value->pinned);
                    if ($client->unpinChat($testChatId) !== false) {
                        throw new RuntimeException('Client pin variants did not round-trip.');
                    }
                    awaitCertificationChat($client, $testChatId, static fn (Chat $value): bool => !$value->pinned);
                }

                $temporaryMute = new DateTimeImmutable('+5 minutes');
                if ($originalMuted) {
                    if ($client->unmuteChat($testChatId)->isMuted) {
                        throw new RuntimeException('Client unmute variant did not clear mute state.');
                    }
                    $originalUntil = $originalMuteExpiration > time()
                        ? new DateTimeImmutable('@'.$originalMuteExpiration)
                        : null;
                    if (!$client->muteChat($testChatId, $originalUntil)->isMuted) {
                        throw new RuntimeException('Client mute variant did not restore mute state.');
                    }
                } else {
                    if (!$client->muteChat($testChatId, $temporaryMute)->isMuted
                        || $client->unmuteChat($testChatId)->isMuted
                    ) {
                        throw new RuntimeException('Client mute variants did not round-trip.');
                    }
                }

                $client->markChatUnread($testChatId);
                if (!$client->sendSeen($testChatId)) throw new RuntimeException('Client could not restore seen state.');
                $chat->markUnread();
                if (!$chat->sendSeen()) throw new RuntimeException('Chat could not restore seen state.');
                if (!$client->syncHistory($testChatId)) throw new RuntimeException('Client could not synchronize chat history.');
                $client->sendPresenceUnavailable();
                $client->sendPresenceAvailable();
            } finally {
                $current = $client->getChatById($testChatId);
                if (!$current instanceof Chat) {
                    throw new RuntimeException('Unable to reload chat while restoring client variants.');
                }
                if ($current->archived !== $originalArchived) {
                    $restored = $originalArchived
                        ? $client->archiveChat($testChatId)
                        : $client->unarchiveChat($testChatId);
                    if ($restored !== $originalArchived) throw new RuntimeException('Unable to restore client archive state.');
                }
                if ($current->pinned !== $originalPinned) {
                    $restored = $originalPinned
                        ? $client->pinChat($testChatId)
                        : $client->unpinChat($testChatId);
                    if ($restored !== $originalPinned) throw new RuntimeException('Unable to restore client pin state.');
                }
                if ($current->isMuted !== $originalMuted || $current->muteExpiration !== $originalMuteExpiration) {
                    if ($originalMuted) {
                        $originalUntil = $originalMuteExpiration > time()
                            ? new DateTimeImmutable('@'.$originalMuteExpiration)
                            : null;
                        if (!$client->muteChat($testChatId, $originalUntil)->isMuted) {
                            throw new RuntimeException('Unable to restore client mute state.');
                        }
                    } elseif ($client->unmuteChat($testChatId)->isMuted) {
                        throw new RuntimeException('Unable to restore client unmute state.');
                    }
                }
                $client->sendSeen($testChatId);
                $client->sendPresenceAvailable();
            }
        });
    }

    $readOnlyGroup = $testGroupId === null
        ? array_find($chats, static fn (object $chat): bool => $chat instanceof GroupChat)
        : null;
    $readOnlyGroupContact = $testGroupId === null && !($readOnlyGroup instanceof GroupChat)
        ? array_find($contacts, static fn (\Pam\WhatsApp\Contact $contact): bool => $contact->isGroup)
        : null;
    $readOnlyGroupId = $testGroupId
        ?? ($readOnlyGroup instanceof GroupChat ? $readOnlyGroup->id->serialized : null)
        ?? $readOnlyGroupContact?->id->serialized;
    if ($readOnlyGroupId === null) {
        $report->skip('group.read', 'No explicit or automatically discoverable group is available.');
    } else {
        $groupInviteCode = null;
        $report->check('group.read', array_merge([
            'member:Client:1:getChatById',
            'member:GroupChat:1:getInviteCode',
            'member:GroupChat:1:getGroupMembershipRequests',
            'member:Client:1:getGroupMembershipRequests',
            'member:Chat:1:fetchMessages',
        ], certificationPropertyCoverage('Chat'), certificationPropertyCoverage('GroupChat')), static function () use ($client, $readOnlyGroupId, &$groupInviteCode): void {
            $group = $client->getChatById($readOnlyGroupId);
            if (!$group instanceof GroupChat) throw new RuntimeException('Configured group did not hydrate as GroupChat.');
            assertCertificationProperties($group, 'Chat');
            assertCertificationProperties($group, 'GroupChat');
            $groupInviteCode = $group->getInviteCode();
            $group->getGroupMembershipRequests();
            $client->getGroupMembershipRequests($readOnlyGroupId);
            $group->fetchMessages(new MessageSearchOptions(limit: 3));
        });
        if ($groupInviteCode === null) {
            $report->skip('group.invite-info', 'The configured group did not expose an invite code.');
        } else {
            $report->check('group.invite-info', [
                'member:Client:1:getInviteInfo',
            ], static function () use ($client, $groupInviteCode): void {
                if ($client->getInviteInfo($groupInviteCode) === []) {
                    throw new RuntimeException('Group invite metadata is empty.');
                }
            });
        }
    }

    if (!$certifyGroupMutations) {
        $report->skip('group.reversible-mutations', 'Set PAM_WWEB_CERTIFY_GROUP_MUTATIONS=1 for a dedicated group.');
    } elseif (!$allowMutations || $testGroupId === null) {
        $report->skip('group.reversible-mutations', 'Group mutation certification requires mutations and PAM_WWEB_TEST_GROUP_ID.');
    } else {
        $report->check('group.reversible-mutations', array_merge([
            'member:Client:1:getChatById',
            'member:GroupChat:1:setSubject',
            'member:GroupChat:1:setDescription',
            'member:Client:3:group_update',
            'member:Events:4:GROUP_UPDATE',
            'member:GroupNotification:1:getChat',
            'member:GroupNotification:1:getContact',
            'member:GroupNotification:1:getRecipients',
            'member:GroupNotification:1:reply',
        ], certificationPropertyCoverage('GroupNotification')), static function () use ($client, $eventProbe, $testGroupId): void {
            $group = $client->getChatById($testGroupId);
            if (!$group instanceof GroupChat) throw new RuntimeException('Configured mutation target is not a group.');
            $originalSubject = $group->name;
            $originalDescription = $group->description;
            $subjectChanged = false;
            $descriptionChanged = false;
            $notificationReply = null;
            try {
                $suffix = substr(hash('sha256', (string) hrtime(true)), 0, 8);
                $subject = 'PAM certification '.$suffix;
                $description = 'PAM reversible certification '.$suffix;
                if (!$group->setSubject($subject)) throw new RuntimeException('Unable to mutate the group subject.');
                $subjectChanged = true;
                if ($group->name !== $subject) throw new RuntimeException('Mutated group subject was not reflected locally.');
                if (!$group->setDescription($description)) throw new RuntimeException('Unable to mutate the group description.');
                $descriptionChanged = true;
                if ($group->description !== $description) {
                    throw new RuntimeException('Mutated group description was not reflected locally.');
                }
                $eventProbe->await($client, [
                    EventType::GroupUpdated->value => \Pam\WhatsApp\Event\GroupNotification::class,
                ], 30.0);
                $notification = $eventProbe->first(EventType::GroupUpdated);
                if (!$notification instanceof \Pam\WhatsApp\Event\GroupNotification) {
                    throw new RuntimeException('Group update event was not hydrated to a group notification.');
                }
                assertCertificationProperties($notification, 'GroupNotification');
                assertCertificationProperties($notification->getChat(), 'Chat');
                assertCertificationProperties($notification->getContact(), 'Contact');
                foreach ($notification->getRecipients() as $recipient) {
                    assertCertificationProperties($recipient, 'Contact');
                }
                $notificationReply = $notification->reply('PAM group notification certification');
                assertCertificationProperties($notificationReply, 'Message');
                assertCertificationProperties($notificationReply->id, 'MessageId');
            } finally {
                $restoreErrors = [];
                try {
                    $notificationReply?->delete(everyone: true, clearMedia: true);
                } catch (Throwable $exception) {
                    $restoreErrors[] = 'notification reply ('.$exception::class.')';
                }
                if ($descriptionChanged) {
                    attemptCertificationRestore('description', static fn (): bool => $group->setDescription($originalDescription), $restoreErrors);
                }
                if ($subjectChanged) {
                    attemptCertificationRestore('subject', static fn (): bool => $group->setSubject($originalSubject), $restoreErrors);
                }
                if ($restoreErrors !== []) {
                    throw new RuntimeException('Unable to restore original group fields: '.implode(', ', $restoreErrors).'.');
                }
            }
        });

        if ($testGroupParticipantId === null) {
            $report->skip('group.participant-role-roundtrip', 'PAM_WWEB_TEST_GROUP_PARTICIPANT_ID was not provided.');
        } else {
            $report->check('group.participant-role-roundtrip', [
                'member:GroupChat:2:promoteParticipants',
                'member:GroupChat:2:demoteParticipants',
            ], static function () use ($client, $testGroupId, $testGroupParticipantId): void {
                $group = $client->getChatById($testGroupId);
                if (!$group instanceof GroupChat) throw new RuntimeException('Configured participant target is not a group.');
                $participant = array_find(
                    $group->participants,
                    static fn (\Pam\WhatsApp\GroupParticipant $candidate): bool => $candidate->id->serialized === $testGroupParticipantId,
                );
                if (!$participant instanceof \Pam\WhatsApp\GroupParticipant || $participant->isSuperAdmin) {
                    throw new RuntimeException('Configured participant was not found or is the group super-admin.');
                }
                $originalAdmin = $participant->isAdmin;
                try {
                    if ($originalAdmin) {
                        $group->demoteParticipants([$testGroupParticipantId]);
                        $group->promoteParticipants([$testGroupParticipantId]);
                    } else {
                        $group->promoteParticipants([$testGroupParticipantId]);
                        $group->demoteParticipants([$testGroupParticipantId]);
                    }
                    $current = $client->getChatById($testGroupId);
                    if (!$current instanceof GroupChat) throw new RuntimeException('Unable to reload participant role.');
                    $roundTripped = array_find(
                        $current->participants,
                        static fn (\Pam\WhatsApp\GroupParticipant $candidate): bool => $candidate->id->serialized === $testGroupParticipantId,
                    );
                    if (!$roundTripped instanceof \Pam\WhatsApp\GroupParticipant || $roundTripped->isAdmin !== $originalAdmin) {
                        throw new RuntimeException('Participant role did not round-trip.');
                    }
                } finally {
                    $current = $client->getChatById($testGroupId);
                    if (!$current instanceof GroupChat) throw new RuntimeException('Unable to reload participant during restoration.');
                    $latest = array_find(
                        $current->participants,
                        static fn (\Pam\WhatsApp\GroupParticipant $candidate): bool => $candidate->id->serialized === $testGroupParticipantId,
                    );
                    if (!$latest instanceof \Pam\WhatsApp\GroupParticipant) {
                        throw new RuntimeException('Participant disappeared during role restoration.');
                    }
                    if ($latest->isAdmin !== $originalAdmin) {
                        if ($originalAdmin) {
                            $current->promoteParticipants([$testGroupParticipantId]);
                        } else {
                            $current->demoteParticipants([$testGroupParticipantId]);
                        }
                    }
                }
            });
        }
    }

    if ($testChannelId === null) {
        $report->skip('channel.read', 'PAM_WWEB_TEST_CHANNEL_ID was not provided.');
    } else {
        $report->check('channel.read', array_merge([
            'member:Client:1:getChatById',
            'member:Channel:1:getSubscribers',
            'member:Channel:1:fetchMessages',
        ], certificationPropertyCoverage('Channel')), static function () use ($client, $testChannelId): void {
            $channel = $client->getChatById($testChannelId);
            if (!$channel instanceof Channel) throw new RuntimeException('Configured channel did not hydrate as Channel.');
            assertCertificationProperties($channel, 'Channel');
            $channel->getSubscribers(3);
            $channel->fetchMessages(new MessageSearchOptions(limit: 3));
        });
    }

    if (!$certifyChannelMutations) {
        $report->skip('channel.reversible-mutations', 'Set PAM_WWEB_CERTIFY_CHANNEL_MUTATIONS=1 for a dedicated owned channel.');
    } elseif (!$allowMutations || $testChannelId === null) {
        $report->skip('channel.reversible-mutations', 'Channel mutation certification requires mutations and PAM_WWEB_TEST_CHANNEL_ID.');
    } else {
        $report->check('channel.reversible-mutations', [
            'member:Client:1:getChatById',
            'member:Channel:1:setSubject',
            'member:Channel:1:setDescription',
            'member:Channel:1:mute',
            'member:Channel:1:unmute',
            'member:Channel:1:sendSeen',
        ], static function () use ($client, $testChannelId): void {
            $channel = $client->getChatById($testChannelId);
            if (!$channel instanceof Channel) throw new RuntimeException('Configured mutation target is not a channel.');
            $originalSubject = $channel->name;
            $originalDescription = $channel->description;
            $originalMuted = $channel->isMuted;
            $subjectChanged = false;
            $descriptionChanged = false;
            $muteChanged = false;
            try {
                $suffix = substr(hash('sha256', (string) hrtime(true)), 0, 8);
                $subject = 'PAM channel '.$suffix;
                $description = 'PAM reversible channel certification '.$suffix;
                if (!$channel->setSubject($subject)) throw new RuntimeException('Unable to mutate the channel subject.');
                $subjectChanged = true;
                if ($channel->name !== $subject) throw new RuntimeException('Mutated channel subject was not reflected locally.');
                if (!$channel->setDescription($description)) {
                    throw new RuntimeException('Unable to mutate the channel description.');
                }
                $descriptionChanged = true;
                if ($channel->description !== $description) {
                    throw new RuntimeException('Mutated channel description was not reflected locally.');
                }
                $muteChanged = $originalMuted ? $channel->unmute() : $channel->mute();
                if (!$muteChanged || $channel->isMuted === $originalMuted) {
                    throw new RuntimeException('Channel mute state did not change.');
                }
                if (!$channel->sendSeen()) throw new RuntimeException('Unable to mark the channel as seen.');
            } finally {
                $restoreErrors = [];
                if ($muteChanged) {
                    attemptCertificationRestore('mute', static function () use ($channel, $originalMuted): bool {
                        $restored = $originalMuted ? $channel->mute() : $channel->unmute();

                        return $restored && $channel->isMuted === $originalMuted;
                    }, $restoreErrors);
                }
                if ($descriptionChanged) {
                    attemptCertificationRestore('description', static fn (): bool => $channel->setDescription($originalDescription), $restoreErrors);
                }
                if ($subjectChanged) {
                    attemptCertificationRestore('subject', static fn (): bool => $channel->setSubject($originalSubject), $restoreErrors);
                }
                if ($restoreErrors !== []) {
                    throw new RuntimeException('Unable to restore original channel fields: '.implode(', ', $restoreErrors).'.');
                }
            }
        });
    }

    if (!$certifyChannelPosts) {
        $report->skip('channel.post-lifecycle', 'Set PAM_WWEB_CERTIFY_CHANNEL_POSTS=1 for a dedicated owned channel.');
    } elseif (!$allowMutations || $testChannelId === null) {
        $report->skip('channel.post-lifecycle', 'Channel post certification requires mutations and PAM_WWEB_TEST_CHANNEL_ID.');
    } else {
        $report->check('channel.post-lifecycle', array_merge([
            'member:Client:1:getChatById',
            'member:Channel:1:sendMessage',
            'member:Message:1:delete',
        ], certificationPropertyCoverage('Message'), certificationPropertyCoverage('MessageId')), static function () use ($client, $testChannelId): void {
            $message = null;
            try {
                $channel = $client->getChatById($testChannelId);
                if (!$channel instanceof Channel) throw new RuntimeException('Configured post target is not a channel.');
                $message = $channel->sendMessage('PAM channel certification '.gmdate('Y-m-d\TH:i:s\Z'));
                assertCertificationProperties($message, 'Message');
                assertCertificationProperties($message->id, 'MessageId');
            } finally {
                $message?->delete(everyone: true, clearMedia: true);
            }
        });
    }

    if (!$allowMutations) {
        $report->skip('message.lifecycle', 'Set PAM_WWEB_ALLOW_MUTATIONS=1 with a dedicated test chat to enable.');
    } elseif ($testChatId === null) {
        $report->skip('message.lifecycle', 'Mutations require PAM_WWEB_TEST_CHAT_ID.');
    } else {
        $report->check('message.lifecycle', array_merge([
            'member:Client:1:getChatById',
            'member:Chat:1:sendStateTyping',
            'member:Chat:1:clearState',
            'member:Chat:1:sendMessage',
            'member:Chat:1:sendSeen',
            'member:Client:1:getMessageById',
            'member:Message:1:star',
            'member:Message:1:unstar',
            'member:Message:1:pin',
            'member:Message:1:unpin',
            'member:Message:1:react',
            'member:Message:1:edit',
            'member:Message:1:reply',
            'member:Message:1:getInfo',
            'member:Message:1:getReactions',
            'member:Message:1:reload',
            'member:Message:1:delete',
        ], certificationPropertyCoverage('Message'), certificationPropertyCoverage('MessageId')), static function () use ($client, $testChatId): void {
            $message = null;
            $reply = null;
            try {
                $chat = $client->getChatById($testChatId);
                if (!$chat instanceof Chat) throw new RuntimeException('Configured mutation target is not a chat.');
                $chat->sendStateTyping();
                $chat->clearState();
                $chat->sendSeen();
                $message = $chat->sendMessage('PAM certification '.gmdate('Y-m-d\TH:i:s\Z'));
                assertCertificationProperties($message, 'Message');
                assertCertificationProperties($message->id, 'MessageId');
                $message->star();
                $message->unstar();
                if (!$message->pin(86_400)) throw new RuntimeException('Sent message could not be pinned.');
                if (!$message->unpin()) throw new RuntimeException('Sent message could not be unpinned.');
                $message->react('✅');
                $edited = $message->edit('PAM certification verified');
                if (!$edited instanceof Message) throw new RuntimeException('Sent message could not be edited.');
                assertCertificationProperties($edited, 'Message');
                assertCertificationProperties($edited->id, 'MessageId');
                $client->getMessageById($edited->id->serialized);
                $edited->getInfo();
                $edited->getReactions();
                $edited->reload();
                $reply = $edited->reply('PAM certification reply');
                assertCertificationProperties($reply, 'Message');
                assertCertificationProperties($reply->id, 'MessageId');
                $message = $edited;
            } finally {
                $reply?->delete(everyone: true, clearMedia: true);
                $message?->delete(everyone: true, clearMedia: true);
            }
        });
    }

    if (!$certifyEvents) {
        $report->skip('message.events', 'Set PAM_WWEB_CERTIFY_EVENTS=1 with message mutations to certify events.');
    } elseif (!$allowMutations || $testChatId === null) {
        $report->skip('message.events', 'Event certification requires mutations and PAM_WWEB_TEST_CHAT_ID.');
    } else {
        $report->check('message.events', array_merge([
            'member:Client:3:message_ack',
            'member:Client:3:message_create',
            'member:Client:3:message_edit',
            'member:Client:3:message_reaction',
            'member:Client:3:message_revoke_everyone',
            'member:Events:4:MESSAGE_ACK',
            'member:Events:4:MESSAGE_CREATE',
            'member:Events:4:MESSAGE_EDIT',
            'member:Events:4:MESSAGE_REACTION',
            'member:Events:4:MESSAGE_REVOKED_EVERYONE',
        ], certificationPropertyCoverage('Reaction')), static function () use ($client, $eventProbe): void {
            $eventProbe->await($client, [
                EventType::MessageAcknowledged->value => \Pam\WhatsApp\Event\MessageAcknowledged::class,
                EventType::MessageCreated->value => \Pam\WhatsApp\Event\MessageLifecycle::class,
                EventType::MessageEdited->value => \Pam\WhatsApp\Event\MessageLifecycle::class,
                EventType::MessageReaction->value => \Pam\WhatsApp\Reaction::class,
                EventType::MessageRevokedEveryone->value => \Pam\WhatsApp\Event\MessageLifecycle::class,
            ], 30.0);
            $reaction = $eventProbe->first(EventType::MessageReaction);
            if (!$reaction instanceof \Pam\WhatsApp\Reaction) {
                throw new RuntimeException('Message reaction event was not hydrated to a reaction.');
            }
            assertCertificationProperties($reaction, 'Reaction');
            assertCertificationProperties($reaction->id, 'MessageId');
            assertCertificationProperties($reaction->msgId, 'MessageId');
        });
    }

    if (!$certifyMedia) {
        $report->skip('media.roundtrip', 'Set PAM_WWEB_CERTIFY_MEDIA=1 with message mutations to certify media.');
    } elseif (!$allowMutations || $testChatId === null) {
        $report->skip('media.roundtrip', 'Media certification requires mutations and PAM_WWEB_TEST_CHAT_ID.');
    } else {
        $report->check('media.roundtrip', array_merge([
            'member:Client:1:sendMessage',
            'member:Message:1:downloadMedia',
            'member:Message:1:delete',
            'member:MessageMedia:1:constructor',
            'member:Client:3:media_uploaded',
            'member:Events:4:MEDIA_UPLOADED',
        ], certificationPropertyCoverage('Message'), certificationPropertyCoverage('MessageId'), certificationPropertyCoverage('MessageMedia')), static function () use ($client, $eventProbe, $testChatId): void {
            $message = null;
            $png = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            );
            if (!is_string($png)) throw new RuntimeException('Embedded certification image is invalid.');
            $media = new MessageMedia('image/png', base64_encode($png), 'pam-certification.png', strlen($png));
            assertCertificationProperties($media, 'MessageMedia');
            $step = 'Client::sendMessage';
            try {
                try {
                    $message = $client->sendMessage($testChatId, $media);
                    $step = 'sent message property audit';
                    assertCertificationProperties($message, 'Message');
                    assertCertificationProperties($message->id, 'MessageId');
                    $step = 'media_uploaded event';
                    $eventProbe->await($client, [
                        EventType::MediaUploaded->value => \Pam\WhatsApp\Event\MessageLifecycle::class,
                    ], 30.0);
                    $step = 'Message::downloadMedia';
                    $downloaded = $message->downloadMedia();
                    if (!$downloaded instanceof MessageMedia
                        || $downloaded->data === ''
                        || base64_decode($downloaded->data, true) === false
                    ) {
                        throw new RuntimeException('Sent media did not round-trip through WhatsApp Web.');
                    }
                    $step = 'downloaded media property audit';
                    assertCertificationProperties($downloaded, 'MessageMedia');
                } catch (Throwable $exception) {
                    throw new RuntimeException("Media step {$step} failed: {$exception->getMessage()}", previous: $exception);
                }
            } finally {
                $message?->delete(everyone: true, clearMedia: true);
            }
        });
    }

    $report->check('client.destroy', [
        'member:Client:1:destroy',
        'member:AuthStrategy:1:destroy',
    ], static function () use ($client, $authStrategy): void {
        $client->destroy();
        if ($client->state !== ClientState::Closed) {
            throw new RuntimeException('Client did not enter the closed state after destroy().');
        }
        if (!in_array('destroy', $authStrategy->observedHooks, true)) {
            throw new RuntimeException('Authentication strategy destroy hook was not observed.');
        }
    });
} finally {
    $client->destroy();
}

$payload = $report->payload($webVersion, $allowMutations);
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
$reportPath = getenv('PAM_WWEB_CERTIFICATION_REPORT');
if (is_string($reportPath) && $reportPath !== '') {
    if (file_put_contents($reportPath, $json, LOCK_EX) !== strlen($json)) {
        throw new RuntimeException('Unable to write certification report.');
    }
}
fwrite(STDOUT, $json);
exit($report->failed() ? 1 : 0);
