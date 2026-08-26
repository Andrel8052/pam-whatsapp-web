<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnrollmentSecurityTest extends TestCase
{
    public function testProtectedScreenshotModeSuppressesRawQrPayloadOutput(): void
    {
        $source = file_get_contents(dirname(__DIR__).'/integration/enroll.php');

        self::assertIsString($source);
        self::assertStringContainsString('if ($headless && $screenshotPath === null)', $source);
        self::assertStringContainsString('PAM_WWEB_QR_PAYLOAD={$event->code}', $source);
        self::assertStringContainsString('protected screenshot pending', $source);
    }
}
