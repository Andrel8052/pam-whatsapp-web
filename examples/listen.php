<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\LocalAuthOptions;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\Event\MessageReceived;
use Pam\WhatsApp\Event\QrCodeReceived;
use Pam\WhatsApp\Event\Ready;
use Pam\WhatsApp\TerminalQrCode;

$client = new Client(new ClientOptions(
    authStrategy: new LocalAuth(new LocalAuthOptions(
        clientId: 'main',
        dataPath: __DIR__.'/.sessions',
    )),
));

$client->onQrCode(static function (QrCodeReceived $event): void {
    echo "\n\nOpen WhatsApp > Linked devices > Link a device\n\n";
    echo TerminalQrCode::render($event->code), "\n";
});

$client->onReady(static function (Ready $event): void {
    echo "\n\n✓ WhatsApp connected. Waiting for messages...\n";
});

$client->onMessage(static function (MessageReceived $event): void {
    $message = $event->message;
    $body = $message->body !== '' ? $message->body : '['.$message->type->name.']';

    printf("[%s] %s: %s\n", date('H:i:s', $message->timestamp), $message->from, $body);
});

$client->initialize();
$client->run();
