<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests\Unit;

use Pam\WhatsApp\TerminalQrCode;
use PHPUnit\Framework\TestCase;

final class TerminalQrCodeTest extends TestCase
{
    public function testItRendersACompactTerminalQrCode(): void
    {
        $qr = TerminalQrCode::render('whatsapp-pairing-payload');
        $lines = explode("\n", $qr);

        self::assertNotEmpty($qr);
        self::assertLessThan(25, count($lines));
        self::assertStringContainsString('█', $qr);
    }

    public function testItRejectsEmptyData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('QR data cannot be empty.');

        TerminalQrCode::render('');
    }
}
