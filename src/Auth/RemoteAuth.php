<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

final class RemoteAuth extends LocalAuth
{
    private readonly RemoteStore $store;

    public readonly int $backupSyncIntervalMilliseconds;

    private bool $restored = false;

    private ?float $nextBackupAt = null;

    private bool $emitOnNextBackup = false;

    private readonly \Closure $clock;

    public function __construct(?RemoteAuthOptions $options = null)
    {
        if ($options === null) {
            throw new \InvalidArgumentException('Remote database store and backup interval are required.');
        }
        if ($options->backupSyncIntervalMs < 60_000) {
            throw new \InvalidArgumentException('RemoteAuth backup interval must be at least 60000 milliseconds.');
        }
        parent::__construct(new LocalAuthOptions(
            $options->clientId,
            $options->dataPath,
            $options->rmMaxRetries,
        ));
        $this->store = $options->store;
        $this->backupSyncIntervalMilliseconds = $options->backupSyncIntervalMs;
        $this->clock = $options->clock ?? static fn (): float => microtime(true);
    }

    public function prepare(): string
    {
        $this->removeProfileDirectory();
        $directory = parent::prepare();
        $session = $this->sessionName();
        if ($this->store->sessionExists(new RemoteStoreOptions($session))) {
            $this->store->extract(new RemoteStoreOptions($session, $directory));
            $this->restored = true;
        }

        return $directory;
    }

    public function afterAuthReady(): void
    {
        $exists = $this->store->sessionExists(new RemoteStoreOptions($this->sessionName()));
        $this->emitOnNextBackup = !$exists;
        $this->nextBackupAt = ($this->clock)() + ($exists ? $this->backupSyncIntervalMilliseconds / 1000 : 60.0);
    }

    public function onPump(string $profileDirectory): bool
    {
        if ($this->nextBackupAt === null || ($this->clock)() < $this->nextBackupAt) {
            return false;
        }
        $this->store->save(new RemoteStoreOptions($this->sessionName(), $profileDirectory));
        $emit = $this->emitOnNextBackup;
        $this->emitOnNextBackup = false;
        $this->nextBackupAt = ($this->clock)() + ($this->backupSyncIntervalMilliseconds / 1000);

        return $emit;
    }

    public function logout(): void
    {
        if ($this->restored || $this->store->sessionExists(new RemoteStoreOptions($this->sessionName()))) {
            $this->store->delete(new RemoteStoreOptions($this->sessionName()));
        }
        $this->restored = false;
        $this->nextBackupAt = null;
        $this->emitOnNextBackup = false;
        parent::logout();
    }

    public function disconnect(): void
    {
        $this->logout();
    }

    public function destroy(): void
    {
        $this->nextBackupAt = null;
        $this->emitOnNextBackup = false;
        parent::destroy();
    }

    protected function profileDirectory(): string
    {
        return $this->dataPath.DIRECTORY_SEPARATOR.($this->clientId === null ? 'RemoteAuth' : 'RemoteAuth-'.$this->clientId);
    }

    private function sessionName(): string
    {
        return $this->clientId === null ? 'RemoteAuth' : 'RemoteAuth-'.$this->clientId;
    }
}
