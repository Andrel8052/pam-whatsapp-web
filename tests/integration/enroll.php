<?php

declare(strict_types=1);

use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\LocalAuthOptions;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\ClientState;
use Pam\WhatsApp\Event\QrCodeReceived;

require dirname(__DIR__).'/bootstrap.php';

$authPath = getenv('PAM_WWEB_AUTH_PATH');
if (!is_string($authPath) || $authPath === '') {
    throw new RuntimeException('PAM_WWEB_AUTH_PATH must identify a dedicated certification profile root.');
}
$timeoutValue = getenv('PAM_WWEB_ENROLL_TIMEOUT_SECONDS');
$timeoutSeconds = is_string($timeoutValue) && $timeoutValue !== ''
    ? filter_var($timeoutValue, FILTER_VALIDATE_INT)
    : 300;
if (!is_int($timeoutSeconds) || $timeoutSeconds < 60 || $timeoutSeconds > 900) {
    throw new InvalidArgumentException('PAM_WWEB_ENROLL_TIMEOUT_SECONDS must be an integer from 60 through 900.');
}
$headless = getenv('PAM_WWEB_HEADLESS') === '1';
$screenshotValue = getenv('PAM_WWEB_QR_SCREENSHOT');
$screenshotPath = null;
if (is_string($screenshotValue) && $screenshotValue !== '') {
    $screenshotFilename = basename($screenshotValue);
    if (str_contains($screenshotValue, "\0") || in_array($screenshotFilename, ['', '.', '..'], true)) {
        throw new InvalidArgumentException('PAM_WWEB_QR_SCREENSHOT must identify a regular file path.');
    }
    if (is_link(dirname($screenshotValue))) {
        throw new InvalidArgumentException('PAM_WWEB_QR_SCREENSHOT parent must not be a symlink.');
    }
    $screenshotDirectory = realpath(dirname($screenshotValue));
    if (!is_string($screenshotDirectory) || !is_dir($screenshotDirectory)) {
        throw new InvalidArgumentException('PAM_WWEB_QR_SCREENSHOT parent must be an existing non-symlink directory.');
    }
    $screenshotPath = $screenshotDirectory.DIRECTORY_SEPARATOR.$screenshotFilename;
}
$client = Client::launch(new ClientOptions(
    authStrategy: new LocalAuth(new LocalAuthOptions('certification', $authPath)),
    headless: $headless,
    browserTimeoutSeconds: 45.0,
    authenticationTimeoutSeconds: (float) $timeoutSeconds,
    browserArguments: ['--disable-dev-shm-usage'],
));
$qrCount = 0;
$capturedQrCount = 0;
$client->onQrCode(static function (QrCodeReceived $event) use (&$qrCount, $headless, $screenshotPath): void {
    $qrCount++;
    if ($headless && $screenshotPath === null) {
        fwrite(STDOUT, "PAM_WWEB_QR_PAYLOAD={$event->code}\n");
    } elseif ($headless) {
        fwrite(STDOUT, "QR #{$qrCount} received; protected screenshot pending.\n");
    } else {
        fwrite(STDOUT, "Scan QR #{$qrCount} in the Chrome window.\n");
    }
});

try {
    $client->initialize();
    $deadline = microtime(true) + $timeoutSeconds;
    while ($client->state !== ClientState::Ready && microtime(true) < $deadline) {
        if ($client->state === ClientState::Failed || $client->state === ClientState::Closed) {
            throw new RuntimeException('WhatsApp enrollment ended before the client became ready.');
        }
        $client->pump(1.0);
        if ($screenshotPath !== null && $qrCount > $capturedQrCount && $client->pupPage !== null) {
            // The QR event can arrive just before WhatsApp paints its canvas.
            usleep(750_000);
            $png = $client->pupPage->captureScreenshot();
            if (file_put_contents($screenshotPath, $png, LOCK_EX) !== strlen($png) || !chmod($screenshotPath, 0600)) {
                throw new RuntimeException('Unable to persist the protected QR screenshot.');
            }
            $capturedQrCount = $qrCount;
            fwrite(STDOUT, "Protected QR screenshot updated at {$screenshotPath}.\n");
        }
    }
    if ($client->state !== ClientState::Ready) {
        throw new RuntimeException('WhatsApp enrollment timed out before the client became ready.');
    }
    fwrite(STDOUT, "Dedicated WhatsApp certification profile enrolled successfully.\n");
} finally {
    $client->close();
}
