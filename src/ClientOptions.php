<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\Browser\Chromium\LaunchOptions;
use Pam\WhatsApp\Auth\AuthStrategy;
use Pam\WhatsApp\Contract\Session;

final readonly class ClientOptions
{
    /**
     * @param list<string> $browserArguments
     */
    public function __construct(
        public ?string $browserExecutable = null,
        public ?string $sessionDirectory = null,
        public ?AuthStrategy $authStrategy = null,
        public bool $headless = true,
        public float $browserTimeoutSeconds = 30.0,
        public float $authenticationTimeoutSeconds = 60.0,
        public array $browserArguments = [],
        public ?int $authTimeoutMs = null,
        public ?string $browserName = null,
        public bool $bypassCSP = false,
        public ?string $deviceName = null,
        public ?string $evalOnNewDoc = null,
        public string $ffmpegPath = 'ffmpeg',
        public ?PairWithPhoneNumber $pairWithPhoneNumber = null,
        public ?ProxyAuthentication $proxyAuthentication = null,
        public ?LaunchOptions $puppeteer = null,
        public int $qrMaxRetries = 0,
        public bool $takeoverOnConflict = false,
        public int $takeoverTimeoutMs = 0,
        public ?string $userAgent = null,
        public ?string $webVersion = '2.3000.1017054665',
        public ?WebVersionCacheOptions $webVersionCache = new LocalWebCacheOptions(),
        public ?Session $session = null,
    ) {
        if ($browserTimeoutSeconds <= 0.0 || $authenticationTimeoutSeconds <= 0.0) {
            throw new \InvalidArgumentException('Browser and authentication timeouts must be positive.');
        }
        if ($authTimeoutMs !== null && $authTimeoutMs < 0) {
            throw new \InvalidArgumentException('Authentication timeout cannot be negative.');
        }
        if ($qrMaxRetries < 0 || $takeoverTimeoutMs < 0) {
            throw new \InvalidArgumentException('QR retries and takeover timeout cannot be negative.');
        }
        if ($browserName !== null && !in_array($browserName, ['Chrome', 'Firefox', 'IE', 'Opera', 'Safari', 'Edge'], true)) {
            throw new \InvalidArgumentException('Unsupported linked-device browser name.');
        }
        foreach ([$deviceName, $evalOnNewDoc, $ffmpegPath, $userAgent, $webVersion] as $value) {
            if ($value !== null && str_contains($value, "\0")) {
                throw new \InvalidArgumentException('Client option strings cannot contain NUL bytes.');
            }
        }
    }

    public function effectiveAuthenticationTimeoutSeconds(): float
    {
        return $this->authTimeoutMs === null ? $this->authenticationTimeoutSeconds : $this->authTimeoutMs / 1000;
    }

    public function effectiveBrowserExecutable(): ?string
    {
        return $this->puppeteer instanceof LaunchOptions
            ? $this->puppeteer->executable
            : $this->browserExecutable;
    }

    public function effectiveHeadless(): bool
    {
        return $this->puppeteer instanceof LaunchOptions ? $this->puppeteer->headless : $this->headless;
    }

    public function effectiveBrowserTimeoutSeconds(): float
    {
        return $this->puppeteer instanceof LaunchOptions
            ? $this->puppeteer->timeoutSeconds
            : $this->browserTimeoutSeconds;
    }

    /** @return list<string> */
    public function effectiveBrowserArguments(): array
    {
        return $this->puppeteer instanceof LaunchOptions ? $this->puppeteer->arguments : $this->browserArguments;
    }

    public function effectiveSessionDirectory(): ?string
    {
        return $this->puppeteer instanceof LaunchOptions
            ? $this->puppeteer->userDataDirectory
            : $this->sessionDirectory;
    }
}
