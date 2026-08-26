<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Support;

final class WebpStickerMetadata
{
    /** @param list<string> $categories */
    public static function apply(string $webp, ?string $name, ?string $author, array $categories): string
    {
        if ($name === null && $author === null) {
            return $webp;
        }
        if (substr($webp, 0, 4) !== 'RIFF' || substr($webp, 8, 4) !== 'WEBP') {
            throw new \UnexpectedValueException('Sticker output is not a WebP RIFF container.');
        }
        $chunks = self::chunks($webp);
        [$width, $height] = self::dimensions($chunks);
        $payload = json_encode([
            'sticker-pack-id' => bin2hex(random_bytes(16)),
            'sticker-pack-name' => $name,
            'sticker-pack-publisher' => $author,
            'emojis' => $categories === [] ? [''] : $categories,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $header = "II*\0\x08\0\0\0\x01\0AW\x07\0\0\0\0\0\x16\0\0\0";
        $header = substr_replace($header, pack('V', strlen($payload)), 14, 4);
        $rebuilt = '';
        $hasExtended = false;
        foreach ($chunks as [$type, $data]) {
            if ($type === 'EXIF') continue;
            if ($type === 'VP8X') {
                $data[0] = chr(ord($data[0]) | 0x08);
                $hasExtended = true;
            }
            $rebuilt .= self::chunk($type, $data);
        }
        if (!$hasExtended) {
            $extended = chr(0x08)."\0\0\0".self::uint24($width - 1).self::uint24($height - 1);
            $rebuilt = self::chunk('VP8X', $extended).$rebuilt;
        }
        $rebuilt .= self::chunk('EXIF', $header.$payload);

        return 'RIFF'.pack('V', strlen($rebuilt) + 4).'WEBP'.$rebuilt;
    }

    /** @return list<array{string, string}> */
    private static function chunks(string $webp): array
    {
        $chunks = [];
        $offset = 12;
        $length = strlen($webp);
        while ($offset + 8 <= $length) {
            $type = substr($webp, $offset, 4);
            $unpacked = unpack('Vsize', substr($webp, $offset + 4, 4));
            $size = is_array($unpacked) ? $unpacked['size'] : null;
            if (!is_int($size) || $size < 0 || $offset + 8 + $size > $length) {
                throw new \UnexpectedValueException('Sticker WebP contains an invalid chunk.');
            }
            $chunks[] = [$type, substr($webp, $offset + 8, $size)];
            $offset += 8 + $size + ($size % 2);
        }
        if ($chunks === []) throw new \UnexpectedValueException('Sticker WebP has no chunks.');

        return $chunks;
    }

    /**
     * @param list<array{string, string}> $chunks
     * @return array{int, int}
     */
    private static function dimensions(array $chunks): array
    {
        foreach ($chunks as [$type, $data]) {
            if ($type === 'VP8X' && strlen($data) >= 10) {
                return [self::readUint24($data, 4) + 1, self::readUint24($data, 7) + 1];
            }
            if ($type === 'VP8 ' && strlen($data) >= 10) {
                return [
                    (ord($data[6]) | (ord($data[7]) << 8)) & 0x3fff,
                    (ord($data[8]) | (ord($data[9]) << 8)) & 0x3fff,
                ];
            }
            if ($type === 'VP8L' && strlen($data) >= 5) {
                $bytes = [ord($data[1]), ord($data[2]), ord($data[3]), ord($data[4])];

                return [1 + $bytes[0] + (($bytes[1] & 0x3f) << 8), 1 + (($bytes[1] >> 6) | ($bytes[2] << 2) | (($bytes[3] & 0x0f) << 10))];
            }
        }

        throw new \UnexpectedValueException('Unable to determine sticker WebP dimensions.');
    }

    private static function chunk(string $type, string $data): string
    {
        return $type.pack('V', strlen($data)).$data.(strlen($data) % 2 === 1 ? "\0" : '');
    }

    private static function uint24(int $value): string
    {
        return chr($value & 0xff).chr(($value >> 8) & 0xff).chr(($value >> 16) & 0xff);
    }

    private static function readUint24(string $data, int $offset): int
    {
        return ord($data[$offset]) | (ord($data[$offset + 1]) << 8) | (ord($data[$offset + 2]) << 16);
    }
}
