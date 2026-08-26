<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

class LocalAuth extends AuthStrategy
{
    public readonly ?string $clientId;
    public readonly string $dataPath;

    public readonly int $rmMaxRetries;

    public function __construct(?LocalAuthOptions $options = null)
    {
        $options ??= new LocalAuthOptions();
        $clientId = $options->clientId;
        $dataPath = $options->dataPath;
        $rmMaxRetries = $options->rmMaxRetries;
        if ($clientId !== null && preg_match('/^[A-Za-z0-9_-]+$/', $clientId) !== 1) {
            throw new \InvalidArgumentException('LocalAuth client id may contain only letters, numbers, underscores, and hyphens.');
        }
        if ($dataPath === null) {
            $workingDirectory = getcwd();
            if ($workingDirectory === false) {
                throw new \RuntimeException('Unable to determine the current working directory for LocalAuth.');
            }
            $dataPath = $workingDirectory.DIRECTORY_SEPARATOR.'.wwebjs_auth';
        }
        if ($dataPath === '' || str_contains($dataPath, "\0") || $rmMaxRetries < 0) {
            throw new \InvalidArgumentException('LocalAuth data path is invalid.');
        }
        $this->clientId = $clientId;
        $this->dataPath = rtrim($dataPath, DIRECTORY_SEPARATOR);
        $this->rmMaxRetries = $rmMaxRetries;
    }

    public function prepare(): string
    {
        $directory = $this->profileDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new \RuntimeException('Unable to create LocalAuth profile directory.');
        }

        return $directory;
    }

    protected function profileDirectory(): string
    {
        return $this->dataPath.DIRECTORY_SEPARATOR.($this->clientId === null ? 'session' : 'session-'.$this->clientId);
    }

    public function logout(): void
    {
        $this->removeProfileDirectory();
    }

    protected function removeProfileDirectory(): void
    {
        $directory = $this->profileDirectory();
        for ($attempt = 0; $attempt <= $this->rmMaxRetries; ++$attempt) {
            if (!is_dir($directory)) return;
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST,
                );
                foreach ($iterator as $entry) {
                    if (!$entry instanceof \SplFileInfo) continue;
                    $path = $entry->getPathname();
                    $removed = $entry->isDir() && !$entry->isLink() ? rmdir($path) : unlink($path);
                    if (!$removed) throw new \RuntimeException('Unable to remove authentication profile entry.');
                }
                if (rmdir($directory)) return;
            } catch (\Throwable $exception) {
                if ($attempt === $this->rmMaxRetries) throw $exception;
            }
        }

        throw new \RuntimeException('Unable to remove authentication profile directory.');
    }
}
