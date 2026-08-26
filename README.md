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

## Complete supported-feature cookbook

The examples below mirror every row in the upstream Supported features table.
They assume an initialized, ready `$client`; IDs such as `$chatId`, `$groupId`,
and `$messageId` must come from your own account. The machine-readable mapping
is [`supported-features.json`](supported-features.json).

Import the classes used by the recipes you copy:

```php
use Pam\WhatsApp\Button;
use Pam\WhatsApp\Buttons;
use Pam\WhatsApp\Chat;
use Pam\WhatsApp\ContactList;
use Pam\WhatsApp\CreateChannelOptions;
use Pam\WhatsApp\GroupChat;
use Pam\WhatsApp\GroupMentionSend;
use Pam\WhatsApp\ListMessage;
use Pam\WhatsApp\Location;
use Pam\WhatsApp\LocationSendOptions;
use Pam\WhatsApp\MessageSendOptions;
use Pam\WhatsApp\Poll;
use Pam\WhatsApp\Event\MessageReceived;
```

<!-- feature:multi-device -->
### Multi Device ✅

Persist the linked-device profile so subsequent starts reconnect without a new QR:

```php
$client = new Client(new ClientOptions(
    authStrategy: new LocalAuth(new LocalAuthOptions('main', __DIR__.'/.sessions')),
));
$client->initialize();
$client->run();
```

<!-- feature:send-messages -->
### Send messages ✅

```php
$client->sendMessageToNumber('+55 (11) 99999-9999', "Hello!\nSecond line.");
$client->sendMessage($chatId, 'Hello using a resolved chat ID.');
```

<!-- feature:receive-messages -->
### Receive messages ✅

```php
$client->onMessage(static function (MessageReceived $event): void {
    printf("%s: %s\n", $event->message->from, $event->message->body);
});
```

<!-- feature:send-media-image-audio-document -->
### Send images, audio, and documents ✅

```php
$client->sendImageToNumber($phone, __DIR__.'/photo.jpg', 'A photo');
$client->sendAudioToNumber($phone, __DIR__.'/voice.ogg', asVoiceNote: true);
$client->sendDocumentToNumber($phone, __DIR__.'/invoice.pdf', 'Invoice');
```

<!-- feature:send-media-video -->
### Send video ✅

Video sending requires Google Chrome, matching the upstream requirement:

```php
$client->sendVideoToNumber($phone, __DIR__.'/demo.mp4', 'Demo video');
$client->sendVideoToNumber($phone, __DIR__.'/animation.mp4', asGif: true);
```

<!-- feature:send-stickers -->
### Send stickers ✅

```php
$client->sendStickerToNumber($phone, __DIR__.'/sticker.webp', 'My pack', 'My app');
```

Video-to-sticker conversion uses the `ffmpegPath` configured in `ClientOptions`.

<!-- feature:receive-media -->
### Receive images, audio, video, and documents ✅

```php
$client->onMessage(static function (MessageReceived $event): void {
    $message = $event->message;
    if (!$message->hasMedia) return;

    $media = $message->downloadMedia();
    if ($media !== null) {
        $binary = base64_decode($media->data, true);
        if ($binary !== false) {
            file_put_contents(__DIR__.'/download.bin', $binary);
        }
    }
});
```

Use `downloadMediaStream()` for large files, as shown later in this README.

<!-- feature:send-contact-cards -->
### Send contact cards ✅

```php
$contact = $client->getContactById('5511999999999@c.us');
$client->sendMessage($chatId, new ContactList([$contact]));
```

<!-- feature:send-location -->
### Send location ✅

```php
$location = new Location(
    -23.5505,
    -46.6333,
    new LocationSendOptions(name: 'São Paulo', address: 'SP, Brazil'),
);
$client->sendMessage($chatId, $location);
```

<!-- feature:send-buttons -->
### Send buttons ❌ deprecated upstream

WhatsApp deprecated this format. The type remains for source compatibility, but
delivery is not guaranteed and new applications should not depend on it:

```php
$legacy = new Buttons('Choose an option', [new Button('Continue', 'continue')]);
$client->sendMessage($chatId, $legacy); // May be rejected by current WhatsApp builds.
```

<!-- feature:send-lists -->
### Send lists ❌ deprecated upstream

```php
$legacy = new ListMessage('Choose a product', 'Open list', [[
    'title' => 'Products',
    'rows' => [['id' => 'coffee', 'title' => 'Coffee']],
]]);
$client->sendMessage($chatId, $legacy); // May be rejected by current WhatsApp builds.
```

<!-- feature:receive-location -->
### Receive location ✅

```php
$client->onMessage(static function (MessageReceived $event): void {
    $location = $event->message->location;
    if ($location !== null) {
        printf("Coordinates: %f, %f\n", $location->latitude, $location->longitude);
    }
});
```

<!-- feature:message-replies -->
### Reply to messages ✅

```php
$client->onMessage(static function (MessageReceived $event): void {
    $event->message->reply('Thanks for your message!');
});
```

<!-- feature:join-groups-by-invite -->
### Join groups by invite ✅

Pass only the invite code, not the complete URL:

```php
$groupId = $client->acceptInvite('AbCdEfGhIjKlMnOpQrStUv');
```

<!-- feature:get-group-invite -->
### Get a group invite ✅

```php
$group = $client->getChatById($groupId);
if ($group instanceof GroupChat) {
    $inviteCode = $group->getInviteCode();
}
```

<!-- feature:modify-group-info -->
### Modify group subject and description ✅

```php
if ($group instanceof GroupChat) {
    $group->setSubject('Customer community');
    $group->setDescription('Support and product announcements');
}
```

<!-- feature:modify-group-settings -->
### Modify group settings ✅

```php
if ($group instanceof GroupChat) {
    $group->setMessagesAdminsOnly(true);
    $group->setInfoAdminsOnly(true);
    $group->setAddMembersAdminsOnly(true);
}
```

<!-- feature:add-group-participants -->
### Add group participants ✅

```php
if ($group instanceof GroupChat) {
    $result = $group->addParticipants(['5511999999999@c.us']);
}
```

<!-- feature:kick-group-participants -->
### Remove group participants ✅

```php
if ($group instanceof GroupChat) {
    $result = $group->removeParticipants(['5511999999999@c.us']);
}
```

<!-- feature:promote-demote-participants -->
### Promote and demote group participants ✅

```php
if ($group instanceof GroupChat) {
    $group->promoteParticipants(['5511999999999@c.us']);
    $group->demoteParticipants(['5511999999999@c.us']);
}
```

<!-- feature:mention-users -->
### Mention users ✅

```php
$client->sendMessage(
    $chatId,
    'Hello @5511999999999',
    new MessageSendOptions(mentions: ['5511999999999@c.us']),
);
```

<!-- feature:mention-groups -->
### Mention groups ✅

```php
$client->sendMessage(
    $chatId,
    'See @Support',
    new MessageSendOptions(groupMentions: [
        new GroupMentionSend('Support', '120363000000000000@g.us'),
    ]),
);
```

<!-- feature:mute-unmute-chats -->
### Mute and unmute chats ✅

```php
$chat = $client->getChatById($chatId);
if ($chat instanceof Chat) {
    $chat->mute(new DateTimeImmutable('+1 hour'));
    $chat->unmute();
}
```

<!-- feature:block-unblock-contacts -->
### Block and unblock contacts ✅

```php
$contact = $client->getContactById('5511999999999@c.us');
$contact->block();
$contact->unblock();
```

<!-- feature:get-contact-info -->
### Get contact information ✅

```php
$contact = $client->getContactById('5511999999999@c.us');
printf("%s (%s)\n", $contact->name, $contact->id->serialized);
```

<!-- feature:get-profile-pictures -->
### Get profile pictures ✅

```php
$url = $client->getProfilePicUrl('5511999999999@c.us');
```

<!-- feature:set-user-status -->
### Set the user status message ✅

```php
$client->setStatus('Available — powered by PAM');
```

<!-- feature:react-to-messages -->
### React to messages ✅

```php
$message = $client->getMessageById($messageId);
$message?->react('👍');
```

<!-- feature:create-polls -->
### Create polls ✅

```php
$poll = new Poll('Where should we have lunch?', ['Pizza', 'Sushi', 'Salad']);
$client->sendMessage($chatId, $poll);
```

<!-- feature:channels -->
### Channels ✅

```php
$channels = $client->getChannels();
$created = $client->createChannel('Product news', new CreateChannelOptions(
    description: 'Release announcements',
));
if (isset($channels[0])) {
    $channels[0]->sendMessage('A new version is available!');
}
```

<!-- feature:vote-in-polls -->
### Vote in polls ✅

```php
$pollMessage = $client->getMessageById($messageId);
$pollMessage?->vote(['Pizza']); // Select one or more options by name.
```

<!-- feature:communities -->
### Communities 🔜 planned upstream

The upstream library does not yet advertise Communities as supported, so this
package intentionally does not claim a complete Communities API. You can inspect
the experimental WhatsApp Web feature flag without treating it as support:

```php
$availableInThisWebBuild = $client->interface?->checkFeatureStatus('communities') ?? false;
```

This flag does not provide community creation or administration guarantees.

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
