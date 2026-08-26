<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Pam\WhatsApp\Support\CompactTerminalQrOutput;

final class TerminalQrCode
{
    public static function render(string $data, string $indent = '  '): string
    {
        if ($data === '') {
            throw new \InvalidArgumentException('QR data cannot be empty.');
        }

        $options = new QROptions([
            'outputInterface' => CompactTerminalQrOutput::class,
            'textLineStart' => $indent,
        ]);

        $output = (new QRCode($options))->render($data);
        if (!is_string($output)) {
            throw new \RuntimeException('Terminal QR renderer returned an invalid value.');
        }

        return $output;
    }
}
