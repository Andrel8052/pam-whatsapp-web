<?php

declare(strict_types=1);

use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\ClientState;
use Pam\WhatsApp\ConnectionState;
use Pam\WhatsApp\Event\QrCodeReceived;
use Pam\WhatsApp\LocalWebCacheOptions;
use Pam\WhatsApp\MediaFromURLOptions;
use Pam\WhatsApp\MessageMedia;
use Pam\WhatsApp\Auth\LocalAuth;

require dirname(__DIR__).'/bootstrap.php';

$cachedVersion = getenv('PAM_WWEB_CACHED_VERSION');
$cachePath = getenv('PAM_WWEB_CACHE_PATH');
$authPath = getenv('PAM_WWEB_AUTH_PATH');
$options = new ClientOptions(
    authStrategy: is_string($authPath) && $authPath !== ''
        ? new LocalAuth(new \Pam\WhatsApp\Auth\LocalAuthOptions('certification', $authPath))
        : null,
    headless: true,
    browserTimeoutSeconds: 45.0,
    authenticationTimeoutSeconds: 75.0,
    browserArguments: ['--disable-dev-shm-usage'],
    webVersion: is_string($cachedVersion) && $cachedVersion !== '' ? $cachedVersion : '2.3000.1017054665',
    webVersionCache: new LocalWebCacheOptions(
        path: is_string($cachePath) && $cachePath !== '' ? $cachePath : null,
        strict: is_string($cachedVersion) && $cachedVersion !== '',
    ),
);
$client = Client::launch($options);
$qrLength = null;
$client->onQrCode(static function (QrCodeReceived $event) use (&$qrLength): void {
    $qrLength = strlen($event->code);
});

try {
    $client->initialize();
    $browserMedia = MessageMedia::fromUrl(
        'data:text/plain;base64,'.base64_encode('pam-browser-media'),
        new MediaFromURLOptions(unsafeMime: true, filename: 'pam.txt', client: $client),
    );
    if ($browserMedia->mimetype !== 'text/plain'
        || $browserMedia->filename !== 'pam.txt'
        || base64_decode($browserMedia->data, true) !== 'pam-browser-media'
        || $browserMedia->filesize !== 17
    ) {
        throw new RuntimeException('Browser-side media download did not preserve its payload metadata.');
    }
    $deadline = microtime(true) + 30.0;
    while ($qrLength === null && $client->state !== ClientState::Ready && microtime(true) < $deadline) {
        $client->pump(1.0);
    }
    if ($client->state === ClientState::AwaitingAuthentication) {
        if (!is_int($qrLength) || $qrLength < 100) {
            throw new RuntimeException('WhatsApp Web did not produce a valid QR payload.');
        }
        $version = $client->getWWebVersion();
        $connectionState = $client->getState();
        if ($version === '' || $connectionState === ConnectionState::Unknown) {
            throw new RuntimeException('WhatsApp Web version or connection state was not exposed.');
        }
        fwrite(STDOUT, sprintf(
            "PAM WhatsApp Web %s installed, state %d, and received a %d-byte QR payload.\n",
            $version,
            $connectionState->value,
            $qrLength,
        ));
    } elseif ($client->state === ClientState::Ready) {
        $client->getChats();
        $client->getChannels();
        $client->getContacts();
        $client->getLabels();
        $client->getBroadcasts();
        $client->getBlockedContacts();
        fwrite(STDOUT, "PAM WhatsApp Web bridge restored an authenticated session.\n");
    } else {
        throw new RuntimeException('WhatsApp client reached an unexpected state.');
    }
} finally {
    $client->close();
}
