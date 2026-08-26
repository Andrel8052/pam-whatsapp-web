<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Support;

use Pam\WhatsApp\Exception\VersionResolveException;
use Pam\WhatsApp\LocalWebCacheOptions;
use Pam\WhatsApp\NoWebCacheOptions;
use Pam\WhatsApp\RemoteWebCacheOptions;
use Pam\WhatsApp\WebVersionCacheOptions;

final readonly class WebVersionCache
{
    public function __construct(private WebVersionCacheOptions $options)
    {
    }

    public function resolve(string $version): ?string
    {
        self::validateVersion($version);
        if ($this->options instanceof NoWebCacheOptions) {
            return null;
        }
        if ($this->options instanceof LocalWebCacheOptions) {
            $content = @file_get_contents($this->localPath($this->options, $version));

            return $this->resolved($content, $version, $this->options->strict, 'cache');
        }

        if ($this->options instanceof RemoteWebCacheOptions) {
            $url = str_replace('{version}', rawurlencode($version), $this->options->remotePath);
            $context = stream_context_create([
                'http' => ['timeout' => $this->options->timeoutSeconds, 'ignore_errors' => true],
            ]);
            $content = @file_get_contents($url, false, $context);

            return $this->resolved($content, $version, $this->options->strict, 'archive');
        }

        throw new \LogicException('Unsupported WhatsApp Web cache configuration.');
    }

    public function persist(string $html, string $version): void
    {
        if (!$this->options instanceof LocalWebCacheOptions) {
            return;
        }
        self::validateVersion($version);
        $path = $this->localPath($this->options, $version);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create WhatsApp Web cache directory.');
        }
        if (file_put_contents($path, $html, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to persist WhatsApp Web cache entry.');
        }
        if (!chmod($path, 0600)) {
            throw new \RuntimeException('Unable to protect WhatsApp Web cache entry.');
        }
    }

    private function localPath(LocalWebCacheOptions $options, string $version): string
    {
        $workingDirectory = getcwd();
        $directory = $options->path ?? (is_string($workingDirectory) ? $workingDirectory : '.').DIRECTORY_SEPARATOR.'.wwebjs_cache';

        return rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$version.'.html';
    }

    private function resolved(string|false $content, string $version, bool $strict, string $source): ?string
    {
        if (is_string($content) && $content !== '') {
            return $content;
        }
        if ($strict) {
            throw new VersionResolveException("Couldn't load version {$version} from the {$source}");
        }

        return null;
    }

    private static function validateVersion(string $version): void
    {
        if ($version === '' || preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $version) !== 1) {
            throw new \InvalidArgumentException('WhatsApp Web version must contain dot-separated numbers.');
        }
    }
}
