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
| Conversations | contacts, private chats, groups, participants, invites, and membership requests |
| Content | locations, polls, contacts, contact lists, scheduled events, and legacy buttons/lists |
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

For unsaved contacts, pass any human-readable international number. The client
normalizes it, verifies that it is registered, resolves its current WhatsApp ID,
and sends the message:

```php
use Pam\WhatsApp\RetryOptions;

$message = $client->sendMessageToNumber(
    '+55 (11) 99999-9999',
    "Hello!\nThis message has two lines.",
    retry: new RetryOptions(maxAttempts: 3),
);
```

Retries are opt-in because retrying an ambiguous send failure can produce a
duplicate. The default is one attempt.

### Send media with one call

```php
$client->sendImageToNumber('+55 11 99999-9999', __DIR__.'/photo.jpg', 'Photo caption');
$client->sendAudioToNumber('+55 11 99999-9999', __DIR__.'/voice.ogg');
$client->sendDocumentToNumber('+55 11 99999-9999', __DIR__.'/invoice.pdf');
$client->sendStickerToNumber('+55 11 99999-9999', __DIR__.'/sticker.webp', 'Pack name', 'Author');
```

### Observe delivery

```php
$client->onMessageSent(fn ($event) => printf("Sent: %s\n", $event->message->id));
$client->onMessageDelivered(fn ($event) => printf("Delivered: %s\n", $event->message->id));
$client->onMessageRead(fn ($event) => printf("Read: %s\n", $event->message->id));
$client->onMessageFailed(fn ($event) => printf("Failed: %s\n", $event->message->id));
```

These convenience events are derived from the typed upstream acknowledgement
event. `on(EventType::MessageAcknowledged, ...)` remains available unchanged.

### Reconnection, diagnostics, and logs

```php
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\LogLevel;

$client = new Client(new ClientOptions(
    autoReconnect: true,
    reconnectMaxAttempts: 5,
    reconnectDelayMs: 1_000,
    logger: static function (LogLevel $level, string $message, array $context): void {
        fwrite(STDERR, sprintf("[%s] %s %s\n", $level->name, $message, json_encode($context)));
    },
));

$diagnostics = $client->diagnoseSession();
if (!$diagnostics->healthy()) {
    var_dump($diagnostics);
}
```

Automatic reconnect is disabled by default to preserve existing production
behavior. Logout and QR retry exhaustion are never reconnected automatically.
`Client::reconnect()` is available after a closed or failed session.

### Upstream supported-feature status

Every feature currently marked ✅ in the upstream `whatsapp-web.js` README is
represented by the typed PHP API: Multi Device, send/receive messages, all
listed media formats, stickers, contact cards, locations, replies, group
administration, mentions, chat mute, contact block, contact/profile operations,
status, reactions, polls, and channels.

Buttons and list messages are deprecated and marked ❌ upstream. Their PHP types
remain for source compatibility, but successful delivery is not guaranteed.
Communities are marked 🔜 upstream and are not advertised as a completed
feature here.

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

The current release tracks `whatsapp-web.js` `1.34.7` at reference commit
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
PAM WhatsApp Web ── typed events and objects
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
