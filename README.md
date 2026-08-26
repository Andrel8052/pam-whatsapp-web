<div align="center">

# PAM WhatsApp Web

### The `whatsapp-web.js` experience, now in persistent, pure PHP.

[![PHP 8.5+](https://img.shields.io/badge/PHP-8.5%2B-777BB4?logo=php&logoColor=white)](https://github.com/push-in/pam)
[![PAM Runtime](https://img.shields.io/badge/runtime-PAM-20C997)](https://github.com/push-in/pam)
[![Packagist](https://img.shields.io/packagist/v/pushinbr/pam-whatsapp-web?color=25c2a0)](https://packagist.org/packages/pushinbr/pam-whatsapp-web)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](LICENSE)

**Terminal QR · persistent sessions · real-time events · media · groups · channels · calls**

[Start here](#start-here) · [Features](#whats-included) · [Compatibility](#compatibility) · [Safety](#responsible-use)

</div>

A typed API for controlling WhatsApp Web directly from PHP. PAM keeps the
process alive, `pam-browser` communicates with Chrome/Chromium through the
Chrome DevTools Protocol, and this library provides the familiar
`whatsapp-web.js` surface — without Node.js, npm, Puppeteer, or Playwright in
production.

## Start here

### 1. Install PAM

```bash
curl --proto '=https' --proto-redir '=https' --tlsv1.2 \
    --connect-timeout 15 --max-time 60 --max-filesize 1048576 -fsSL \
    https://github.com/push-in/pam/releases/latest/download/install.sh | sh

pam doctor
```

### 2. Create your project and install the library

```bash
mkdir my-whatsapp && cd my-whatsapp
pam composer init --no-interaction
pam composer require pushinbr/pam-whatsapp-web:^1.0
```

Chrome or Chromium must be installed on the host. No JavaScript runtime is
required.

### 3. Create `listen.php`

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

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
    $qr = new QRCode(new QROptions([
        'outputInterface' => QRStringText::class,
        'textDark' => "\033[40m  \033[0m",
        'textLight' => "\033[47m  \033[0m",
        'textLineStart' => '  ',
    ]));

    echo "\n\nOpen WhatsApp > Linked devices > Link a device\n\n";
    echo $qr->render($event->code), "\n";
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
```

### 4. Run it

```bash
pam listen.php
```

On the first run, a scannable QR code appears in the terminal. After scanning,
the client connects automatically and starts printing inbound messages.
Authentication is stored in `.sessions/`; subsequent runs restore the session
without another QR code. Never publish this directory or share its contents.

> The complete runnable program is available at [`examples/listen.php`](examples/listen.php).

## What's included

| Area | Features |
|---|---|
| Session | QR, pairing code, `LocalAuth`, `RemoteAuth`, reconnection, and session conflict handling |
| Messages | text, replies, editing, deletion, forwarding, reactions, mentions, and read receipts |
| Media | images, audio, video, documents, stickers, thumbnails, and streaming downloads |
| Conversations | contacts, private chats, groups, participants, invites, communities, and membership requests |
| Content | locations, polls, contacts, contact lists, buttons, list messages, and scheduled events |
| WhatsApp | presence, connection state, calls, channels, labels, business profiles, products, and orders |
| API | typed objects, integer-backed enums, immutable events, PHPStan level 9, and an automated parity matrix |

### Reply to a message

```php
$client->onMessage(static function (MessageReceived $event): void {
    if ($event->message->body === '!ping') {
        $event->message->reply('pong 🟢');
    }
});
```

### Send messages and manage chats

```php
$client->sendMessage('15551234567@c.us', 'Hello directly from PHP!');
$client->sendPresenceAvailable();
$client->archiveChat($chatId);
$client->muteChat($chatId, new DateTimeImmutable('+1 hour'));
$contact = $client->getNumberId('5511999999999');
```

### Download large media without exhausting memory

```php
use Pam\WhatsApp\MediaStreamOptions;

$media = $message->downloadMediaStream(new MediaStreamOptions(
    chunkSize: 1024 * 1024,
));

if ($media !== null) {
    foreach ($media->stream as $chunk) {
        // Persist or forward each binary chunk.
    }
}
```

## Compatibility

Version `1.0` tracks `whatsapp-web.js` `1.34.7` at reference commit
`942d236a11ad68807308b058303ba5256915979c`. Coverage is auditable in
[`api-matrix.json`](api-matrix.json): **81 symbols + 670 members, 751/751 strict
contracts**.

```bash
pam composer parity:gate
pam composer test
pam composer analyse
```

Live certification is split between an unauthenticated QR smoke test and an
authenticated suite with explicit mutation guards. See
[`CERTIFICATION.md`](CERTIFICATION.md).

## Architecture

```text
your PHP code
      │
      ▼
PAM WhatsApp Web ── eventos e objetos tipados
      │
      ▼
PAM Browser ─────── Chrome DevTools Protocol
      │
      ▼
Chrome / Chromium ─ WhatsApp Web
```

## Responsible use

This is an unofficial, community-maintained library. WhatsApp may change its
internal modules without notice and may restrict or block accounts that violate
its terms. Avoid spam and abusive automation. For officially supported
integrations and critical workloads, use Meta's WhatsApp Business Platform.

## License

Open source under the [Apache License 2.0](LICENSE). You may use, modify, and
distribute it, including commercially.
