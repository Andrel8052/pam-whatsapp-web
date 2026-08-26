<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Support;

use Pam\WhatsApp\Exception\BridgeException;

final class Payload
{
    /** @return array<string, mixed> */
    public static function object(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new BridgeException($label.' must be an object.');
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new BridgeException($label.' must use string keys.');
            }
            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /** @return list<array<string, mixed>> */
    public static function objects(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new BridgeException($label.' must be a list.');
        }
        $objects = [];
        foreach ($value as $item) {
            $objects[] = self::object($item, $label.' item');
        }

        return $objects;
    }

    /** @param array<string, mixed> $payload */
    public static function id(array $payload): string
    {
        $id = $payload['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }
        if (is_array($id) && is_string($id['_serialized'] ?? null) && $id['_serialized'] !== '') {
            return $id['_serialized'];
        }

        throw new BridgeException('Domain payload does not contain a serialized id.');
    }

    /** @param array<string, mixed> $payload */
    public static function string(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /** @param array<string, mixed> $payload */
    public static function bool(array $payload, string $key): bool
    {
        return ($payload[$key] ?? false) === true;
    }

    /** @param array<string, mixed> $payload */
    public static function int(array $payload, string $key): int
    {
        $value = $payload[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }
}
