<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests;

use Pam\Browser\Chromium\LaunchOptions;
use Pam\WhatsApp\Auth\AuthenticationDecision;
use Pam\WhatsApp\Auth\AuthenticationHandshake;
use Pam\WhatsApp\Auth\AuthStrategy;
use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\LocalAuthOptions;
use Pam\WhatsApp\Auth\NoAuth;
use Pam\WhatsApp\Auth\RemoteAuth;
use Pam\WhatsApp\Auth\RemoteAuthOptions;
use Pam\WhatsApp\Auth\RemoteStore;
use Pam\WhatsApp\Auth\RemoteStoreOptions;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\BrowserSession;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\EventType;
use PHPUnit\Framework\TestCase;

final class AuthStrategyTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pam-whatsapp-auth-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testNoAuthUsesAnEphemeralBrowserProfile(): void
    {
        $auth = new NoAuth();

        self::assertNull($auth->prepare());
        $auth->beforeBrowserInitialized();
        $auth->afterBrowserInitialized();
        self::assertSame([
            'failed' => false,
            'restart' => false,
            'failureEventPayload' => null,
        ], $auth->onAuthenticationNeeded());
        self::assertNull($auth->getAuthEventPayload());
        $auth->afterAuthReady();
        $auth->disconnect();
        $auth->destroy();
    }

    public function testCustomStrategyDoesNotNeedToImplementInternalProfilePreparation(): void
    {
        $strategy = new class extends AuthStrategy {
        };
        $client = new Client(new ClientOptions(authStrategy: $strategy));

        self::assertNull($strategy->prepare());
        self::assertSame(\Pam\WhatsApp\ClientState::Created, $client->state);
    }

    public function testAuthenticationDecisionNormalizesHookResultsAndPayloads(): void
    {
        $accepted = AuthenticationDecision::fromResult([]);
        self::assertFalse($accepted->failed);
        self::assertFalse($accepted->restart);

        $failed = AuthenticationDecision::fromResult([
            'failed' => true,
            'restart' => true,
            'failureEventPayload' => ['reason' => 'expired'],
        ]);
        self::assertTrue($failed->failed);
        self::assertTrue($failed->restart);
        self::assertSame('{"reason":"expired"}', $failed->failureMessage());
        self::assertSame(
            'Authentication failed.',
            (new AuthenticationDecision(true))->failureMessage(),
        );
    }

    public function testAuthenticationDecisionRejectsInvalidFlags(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        AuthenticationDecision::fromResult(['failed' => 1]);
    }

    public function testAuthenticationDecisionRejectsRestartWithoutFailure(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AuthenticationDecision(restart: true);
    }

    public function testAuthenticationHandshakeCallsStrategyOnceBeforeQrOrPairingCode(): void
    {
        $strategy = new class extends AuthStrategy {
            public int $calls = 0;

            public function prepare(): ?string
            {
                return null;
            }

            public function onAuthenticationNeeded(): array
            {
                ++$this->calls;

                return ['failed' => true, 'restart' => true, 'failureEventPayload' => 'expired'];
            }
        };
        $handshake = new AuthenticationHandshake($strategy);

        self::assertNull($handshake->inspect(EventType::StateChanged));
        $decision = $handshake->inspect(EventType::QrCode);
        self::assertNotNull($decision);
        self::assertTrue($decision->failed);
        self::assertTrue($decision->restart);
        self::assertSame('expired', $decision->failureMessage());
        self::assertNull($handshake->inspect(EventType::PairingCodeReceived));
        self::assertSame(1, $strategy->calls);
    }

    public function testAuthenticationHandshakeWithoutStrategyAcceptsQr(): void
    {
        self::assertNull((new AuthenticationHandshake(null))->inspect(EventType::QrCode));
    }

    public function testClientSetupPrecedesDeferredBrowserLifecycleHooks(): void
    {
        $strategy = new class extends AuthStrategy {
            /** @var list<string> */
            public array $events = [];

            public function setup(Client $client): void
            {
                parent::setup($client);
                $this->events[] = 'setup';
            }

            public function prepare(): ?string
            {
                $this->events[] = 'prepare';

                return null;
            }

            public function beforeBrowserInitialized(): void
            {
                $this->events[] = 'before';
            }
        };
        $session = BrowserSession::create(new ClientOptions(
            authStrategy: $strategy,
            browserExecutable: '/path/that/does/not/exist/pam-chromium',
        ));

        self::assertNull($session->currentBrowser());
        Client::forSession($session);

        self::assertSame(['setup'], $strategy->events);
        self::assertNull($session->currentBrowser());
        try {
            $session->launchBrowser();
            self::fail('A missing Chromium executable must fail the deferred launch.');
        } catch (\RuntimeException) {
        }
        self::assertSame(['setup', 'prepare', 'before'], $strategy->events);
    }

    public function testClientConstructorAcceptsUpstreamStyleOptionsWithoutLaunchingChromium(): void
    {
        $strategy = new class extends AuthStrategy {
            public bool $wasSetup = false;

            public function setup(Client $client): void
            {
                parent::setup($client);
                $this->wasSetup = true;
            }

            public function prepare(): ?string
            {
                throw new \LogicException('Construction must not launch Chromium.');
            }
        };

        $client = new Client(new ClientOptions(authStrategy: $strategy));

        self::assertTrue($strategy->wasSetup);
        self::assertSame(\Pam\WhatsApp\ClientState::Created, $client->state);
        self::assertNull($client->pupBrowser);
    }

    public function testLocalAuthCreatesAnIsolatedClientProfile(): void
    {
        $auth = new LocalAuth(new LocalAuthOptions('customer_42', $this->temporaryDirectory));

        $profile = $auth->prepare();

        self::assertSame($this->temporaryDirectory.DIRECTORY_SEPARATOR.'session-customer_42', $profile);
        self::assertDirectoryExists($profile);
        file_put_contents($profile.DIRECTORY_SEPARATOR.'session.data', 'secret');
        $auth->logout();
        self::assertDirectoryDoesNotExist($profile);
    }

    public function testLocalAuthRejectsUnsafeClientIdentifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LocalAuth(new LocalAuthOptions('../another-profile', $this->temporaryDirectory));
    }

    public function testRemoteAuthRestoresSavesAndDeletesAStoredSession(): void
    {
        $store = new RecordingRemoteStore(true);
        $time = 1_000.0;
        $auth = new RemoteAuth(new RemoteAuthOptions(
            store: $store,
            backupSyncIntervalMs: 300_000,
            clientId: 'primary',
            dataPath: $this->temporaryDirectory,
            clock: static function () use (&$time): float {
                return $time;
            },
        ));

        $profile = $auth->prepare();
        self::assertSame($this->temporaryDirectory.DIRECTORY_SEPARATOR.'RemoteAuth-primary', $profile);
        $auth->afterAuthReady();
        $time += 300.0;
        self::assertFalse($auth->onPump($profile));
        $auth->logout();

        self::assertSame([['RemoteAuth-primary', $profile]], $store->extractions);
        self::assertSame([['RemoteAuth-primary', $profile]], $store->saves);
        self::assertSame(['RemoteAuth-primary'], $store->deletions);
        self::assertDirectoryDoesNotExist($profile);
    }

    public function testRemoteAuthDelaysAndEmitsOnlyTheFirstNewSessionBackup(): void
    {
        $store = new RecordingRemoteStore(false);
        $time = 100.0;
        $auth = new RemoteAuth(new RemoteAuthOptions(
            store: $store,
            backupSyncIntervalMs: 60_000,
            dataPath: $this->temporaryDirectory,
            clock: static function () use (&$time): float {
                return $time;
            },
        ));
        $profile = $auth->prepare();

        $auth->afterAuthReady();
        $time = 159.9;
        self::assertFalse($auth->onPump($profile));
        $time = 160.0;
        self::assertTrue($auth->onPump($profile));
        $time = 220.0;
        self::assertFalse($auth->onPump($profile));
        self::assertCount(2, $store->saves);
    }

    public function testDestroyPreservesAuthenticationWhileLogoutRemovesIt(): void
    {
        $local = new LocalAuth(new LocalAuthOptions(dataPath: $this->temporaryDirectory));
        $localProfile = $local->prepare();
        file_put_contents($localProfile.DIRECTORY_SEPARATOR.'credentials', 'local');
        $local->disconnect();
        $local->destroy();
        self::assertFileExists($localProfile.DIRECTORY_SEPARATOR.'credentials');

        $store = new RecordingRemoteStore(true);
        $remote = new RemoteAuth(new RemoteAuthOptions($store, 300_000, dataPath: $this->temporaryDirectory));
        $remoteProfile = $remote->prepare();
        $remote->destroy();
        self::assertSame([], $store->deletions);
        self::assertDirectoryExists($remoteProfile);
        $remote->logout();
        self::assertSame(['RemoteAuth'], $store->deletions);
        self::assertDirectoryDoesNotExist($remoteProfile);
    }

    public function testRemoteAuthRequiresASafeBackupInterval(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RemoteAuth(new RemoteAuthOptions(
            new RecordingRemoteStore(false),
            59_999,
            dataPath: $this->temporaryDirectory,
        ));
    }

    public function testClientOptionsUseTypedPamLaunchOptions(): void
    {
        $options = new ClientOptions(
            authenticationTimeoutSeconds: 60,
            authTimeoutMs: 5_000,
            puppeteer: new LaunchOptions(
                executable: '/usr/bin/chromium',
                userDataDirectory: '/tmp/pam-profile',
                headless: false,
                timeoutSeconds: 10.0,
                arguments: ['--no-sandbox'],
            ),
        );

        self::assertSame(5.0, $options->effectiveAuthenticationTimeoutSeconds());
        self::assertSame('/usr/bin/chromium', $options->effectiveBrowserExecutable());
        self::assertFalse($options->effectiveHeadless());
        self::assertSame(['--no-sandbox'], $options->effectiveBrowserArguments());
        self::assertSame(10.0, $options->effectiveBrowserTimeoutSeconds());
        self::assertSame('/tmp/pam-profile', $options->effectiveSessionDirectory());
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}

final class RecordingRemoteStore implements RemoteStore
{
    /** @var list<array{string, string}> */
    public array $extractions = [];

    /** @var list<array{string, string}> */
    public array $saves = [];

    /** @var list<string> */
    public array $deletions = [];

    public function __construct(private bool $exists)
    {
    }

    public function sessionExists(RemoteStoreOptions $options): bool
    {
        return $this->exists;
    }

    public function extract(RemoteStoreOptions $options): void
    {
        $this->extractions[] = [$options->session, $options->path ?? ''];
    }

    public function save(RemoteStoreOptions $options): void
    {
        $this->saves[] = [$options->session, $options->path ?? ''];
        $this->exists = true;
    }

    public function delete(RemoteStoreOptions $options): void
    {
        $this->deletions[] = $options->session;
        $this->exists = false;
    }
}
