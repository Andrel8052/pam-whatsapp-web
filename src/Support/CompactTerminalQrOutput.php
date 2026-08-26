<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Support;

use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputAbstract;

final class CompactTerminalQrOutput extends QROutputAbstract
{
    public const MIME_TYPE = 'text/plain';

    public static function moduleValueIsValid(mixed $value): bool
    {
        return is_string($value);
    }

    protected function prepareModuleValue(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Terminal QR module values must be strings.');
        }

        return $value;
    }

    protected function getDefaultModuleValue(bool $isDark): string
    {
        return $isDark ? '1' : '0';
    }

    public function dump(?string $file = null): string
    {
        $matrix = $this->matrix->getMatrix();
        $lines = [];
        $lineStart = $this->options->toArray()['textLineStart'] ?? '';
        if (!is_string($lineStart)) {
            throw new \InvalidArgumentException('Terminal QR indentation must be a string.');
        }

        for ($row = 0, $height = count($matrix); $row < $height; $row += 2) {
            $top = $matrix[$row];
            $bottom = $matrix[$row + 1] ?? array_fill(0, count($top), QRMatrix::M_NULL);
            $line = $lineStart;

            foreach ($top as $column => $module) {
                $line .= self::block(self::isDark($module), self::isDark($bottom[$column]));
            }

            $lines[] = $line;
        }

        $data = implode($this->eol, $lines);
        $this->saveToFile($data, $file);

        return $data;
    }

    private static function isDark(int $module): bool
    {
        return ($module & QRMatrix::IS_DARK) === QRMatrix::IS_DARK;
    }

    private static function block(bool $top, bool $bottom): string
    {
        return match ([$top, $bottom]) {
            [true, true] => '█',
            [true, false] => '▀',
            [false, true] => '▄',
            default => ' ',
        };
    }
}
