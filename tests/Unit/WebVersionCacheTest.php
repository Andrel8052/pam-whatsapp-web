<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests\Unit;

use Pam\WhatsApp\Exception\VersionResolveException;
use Pam\WhatsApp\LocalWebCacheOptions;
use Pam\WhatsApp\NoWebCacheOptions;
use Pam\WhatsApp\RemoteWebCacheOptions;
use Pam\WhatsApp\Support\WebVersionCache;
use PHPUnit\Framework\TestCase;

final class WebVersionCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pam-wweb-cache-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'2.3000.1.html';
        if (is_file($path)) unlink($path);
        if (is_dir($this->directory)) rmdir($this->directory);
    }

    public function testLocalCachePersistsAndResolvesVersionedHtml(): void
    {
        $cache = new WebVersionCache(new LocalWebCacheOptions($this->directory));
        $cache->persist('<!doctype html><title>PAM</title>', '2.3000.1');

        self::assertSame('<!doctype html><title>PAM</title>', $cache->resolve('2.3000.1'));
        self::assertSame(0600, fileperms($this->directory.'/2.3000.1.html') & 0777);
    }

    public function testNonStrictCachesFallBackAndStrictCacheFails(): void
    {
        self::assertNull((new WebVersionCache(new NoWebCacheOptions()))->resolve('2.3000.1'));
        self::assertNull((new WebVersionCache(new LocalWebCacheOptions($this->directory)))->resolve('2.3000.1'));

        $this->expectException(VersionResolveException::class);
        (new WebVersionCache(new LocalWebCacheOptions($this->directory, true)))->resolve('2.3000.1');
    }

    public function testRemoteCacheResolvesConfiguredArchive(): void
    {
        $url = 'data:text/plain;base64,'.base64_encode('<html>remote</html>');
        $cache = new WebVersionCache(new RemoteWebCacheOptions($url, true));

        self::assertSame('<html>remote</html>', $cache->resolve('2.3000.1'));
    }

    public function testVersionCannotEscapeTheLocalCacheDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WebVersionCache(new LocalWebCacheOptions($this->directory)))->resolve('../secret');
    }
}
