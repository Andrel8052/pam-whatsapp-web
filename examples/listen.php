<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use chillerlan\QRCode\Output\QRStringText;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\LocalAuthOptions;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\Event\MessageReceived;
use Pam\WhatsApp\Event\QrCodeReceived;
use Pam\WhatsApp\Event\Ready;

$client = new Client(new ClientOptions(
    authStrategy: new LocalAuth(new LocalAuthOptions(
        clientId: 'main',
        dataPath: __DIR__.'/.sessions',
    )),
));

$client->onQrCode(static function (QrCodeReceived $event): void {
    $options = new QROptions([
        'outputInterface' => QRStringText::class,
        'eol' => "\n",
        'textDark' => "\033[40m  \033[0m",
        'textLight' => "\033[47m  \033[0m",
        'textLineStart' => '  ',
    ]);

    echo "\033[2J\033[H";
    echo "Abra o WhatsApp > Aparelhos conectados > Conectar aparelho\n\n";
    echo (new QRCode($options))->render($event->code), "\n";
});

$client->onReady(static function (Ready $event): void {
    echo "\033[2J\033[H";
    echo "✓ WhatsApp conectado. Aguardando mensagens...\n";
});

$client->onMessage(static function (MessageReceived $event): void {
    $message = $event->message;
    $body = $message->body !== '' ? $message->body : '['.$message->type->name.']';

    printf("[%s] %s: %s\n", date('H:i:s', $message->timestamp), $message->from, $body);
});

$client->initialize();
$client->run();
