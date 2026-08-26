<?php

declare(strict_types=1);

use Pam\WhatsApp\Bridge\BridgeEvent;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientState;
use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Event\MessageReceived;
use Pam\WhatsApp\Event\QrCodeReceived;
use Pam\WhatsApp\Event\Ready;
use Pam\WhatsApp\EventType;
use Pam\WhatsApp\MessageContentType;
use Pam\WhatsApp\MessageType;

require __DIR__.'/bootstrap.php';

/** @param mixed $expected @param mixed $actual */
function assertWhatsAppSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

final class FakeWhatsAppSession implements Session
{
    /** @var null|callable(BridgeEvent): void */
    private $listener = null;

    public ?string $quotedMessageId = null;

    public function initialize(callable $listener): void
    {
        $this->listener = $listener;
        $listener(new BridgeEvent(EventType::QrCode, ['code' => 'qr-payload']));
        $listener(new BridgeEvent(EventType::Authenticated, ['timestamp' => 100]));
        $listener(new BridgeEvent(EventType::Ready, ['timestamp' => 101]));
    }

    public function sendText(string $chatId, string $body, ?string $quotedMessageId = null): MessageData
    {
        $this->quotedMessageId = $quotedMessageId;

        return new MessageData(
            'sent-id',
            $chatId,
            'me@c.us',
            $chatId,
            $body,
            true,
            102,
            MessageType::Text,
            MessageContentType::Text,
        );
    }

    public function sendContent(string $chatId, array $content, array $options = []): MessageData
    {
        $this->quotedMessageId = is_string($options['quotedMessageId'] ?? null)
            ? $options['quotedMessageId']
            : null;
        $body = is_string($content['text'] ?? null) ? $content['text'] : '';

        return $this->sendText($chatId, $body, $this->quotedMessageId);
    }

    public function pump(float $timeoutSeconds): bool
    {
        $listener = $this->listener;
        if ($listener === null) {
            return false;
        }
        $this->listener = null;
        $listener(new BridgeEvent(EventType::MessageReceived, [
            'id' => 'incoming-id',
            'chatId' => '5511999999999@c.us',
            'from' => '5511999999999@c.us',
            'to' => 'me@c.us',
            'body' => '!ping',
            'fromMe' => false,
            'timestamp' => 103,
            'type' => MessageType::Text->value,
            'contentType' => MessageContentType::Text->value,
        ]));

        return true;
    }

    public function invoke(string $method, array $arguments = []): mixed
    {
        return match ($method) {
            'getWWebVersion' => '2.3000.0',
            'getState' => 1,
            default => null,
        };
    }

    public function close(): void
    {
    }

    public function logout(): void
    {
    }
}

$session = new FakeWhatsAppSession();
$client = Client::forSession($session);
$qr = null;
$ready = null;
$reply = null;
$client->onQrCode(static function (QrCodeReceived $event) use (&$qr): void {
    $qr = $event->code;
});
$client->onReady(static function (Ready $event) use (&$ready): void {
    $ready = $event->timestamp;
});
$client->onMessage(static function (MessageReceived $event) use (&$reply): void {
    $reply = $event->message->reply('pong');
});

$client->initialize();
assertWhatsAppSame('qr-payload', $qr, 'QR event was not dispatched.');
assertWhatsAppSame(101, $ready, 'Ready event was not dispatched.');
assertWhatsAppSame(ClientState::Ready, $client->state, 'Client did not reach ready state.');

$client->pump();
assertWhatsAppSame('pong', $reply?->body, 'Message reply did not use the session.');
assertWhatsAppSame('incoming-id', $session->quotedMessageId, 'Reply did not quote the source message.');

$sent = $client->sendMessage('5511888888888@c.us', 'hello');
assertWhatsAppSame('hello', $sent->body, 'Direct message body was not preserved.');

$client->close();
assertWhatsAppSame(ClientState::Closed, $client->state, 'Client did not close.');

fwrite(STDOUT, "PAM WhatsApp client tests passed.\n");
