<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\LocalAuthOptions;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\Event\Ready;

$client = new Client(new ClientOptions(
    authStrategy: new LocalAuth(new LocalAuthOptions('main', __DIR__.'/.sessions')),
));

$client->onReady(static function (Ready $event) use ($client): void {
    $message = $client->sendMessageToNumber(
        '+55 (11) 99999-9999',
        "Hello!\nSent from pure PHP.",
    );

    printf("Sent message %s\n", $message->id);
});

$client->onMessageDelivered(static fn ($event) => printf("Delivered %s\n", $event->message->id));
$client->onMessageRead(static fn ($event) => printf("Read %s\n", $event->message->id));

$client->initialize();
$client->run();
