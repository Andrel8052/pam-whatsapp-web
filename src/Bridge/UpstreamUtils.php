<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Bridge;

use Pam\WhatsApp\Exception\BridgeException;

final class UpstreamUtils
{
    private const SHA256 = '92aab2d93fad171d4c3e596b6cfa760b3ecfd05daeaf56e18ef490a582b8411f';

    public static function functionSource(): string
    {
        $path = dirname(__DIR__, 2).'/resources/upstream/Utils.js';
        $source = @file_get_contents($path);
        if (!is_string($source)) {
            throw new BridgeException('Pinned whatsapp-web.js Utils.js asset is missing.');
        }
        if (!hash_equals(self::SHA256, hash('sha256', $source))) {
            throw new BridgeException('Pinned whatsapp-web.js Utils.js asset failed integrity validation.');
        }

        $prefix = "'use strict';\n\nexports.LoadUtils = () => {";
        if (!str_starts_with($source, $prefix) || !str_ends_with(trim($source), '};')) {
            throw new BridgeException('Pinned whatsapp-web.js Utils.js has an unexpected module wrapper.');
        }
        $body = substr($source, strlen($prefix));
        $body = substr(rtrim($body), 0, -2);

        return "globalThis.__pamLoadWWebJs = () => {".$body."};";
    }
}
