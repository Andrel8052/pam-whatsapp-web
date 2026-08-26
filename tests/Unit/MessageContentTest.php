<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests\Unit;

use DateTimeImmutable;
use Pam\WhatsApp\ContentKind;
use Pam\WhatsApp\Button;
use Pam\WhatsApp\Buttons;
use Pam\WhatsApp\ContactList;
use Pam\WhatsApp\ListMessage;
use Pam\WhatsApp\Location;
use Pam\WhatsApp\MessageMedia;
use Pam\WhatsApp\MediaFromURLOptions;
use Pam\WhatsApp\Support\StickerFormatter;
use Pam\WhatsApp\Support\WebpStickerMetadata;
use Pam\WhatsApp\Poll;
use Pam\WhatsApp\ScheduledEvent;
use Pam\WhatsApp\ScheduledEventCallType;
use Pam\WhatsApp\ChatType;
use Pam\WhatsApp\EventType;
use Pam\WhatsApp\GroupNotificationType;
use Pam\WhatsApp\MessageType;
use Pam\WhatsApp\GroupMentionSend;
use Pam\WhatsApp\MessageSendOptions;
use PHPUnit\Framework\TestCase;

final class MessageContentTest extends TestCase
{
    public function testMessageMediaCanBeLoadedFromAUrl(): void
    {
        $media = MessageMedia::fromUrl(
            'data:text/plain;base64,'.base64_encode('community'),
            new MediaFromURLOptions(unsafeMime: true, filename: 'community.txt'),
        );

        self::assertSame('text/plain', $media->mimetype);
        self::assertSame('community.txt', $media->filename);
        self::assertSame('community', base64_decode($media->data, true));
        self::assertSame(9, $media->filesize);
    }

    public function testMessageMediaUrlRequiresDiscoverableMimeUnlessExplicitlyUnsafe(): void
    {
        $this->expectException(\RuntimeException::class);
        MessageMedia::fromUrl('https://example.test/download');
    }

    public function testVideoStickerConversionReportsAnUnavailableFfmpegBinary(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StickerFormatter('/definitely-not-a-pam-ffmpeg-binary'))->formatVideo(
            new MessageMedia('video/mp4', base64_encode('not-a-video')),
        );
    }

    public function testFfmpegProducesAWebpStickerWhenAvailable(): void
    {
        if (!is_executable('/usr/bin/ffmpeg')) self::markTestSkipped('FFmpeg is not installed.');
        $formatted = (new StickerFormatter('/usr/bin/ffmpeg'))->formatVideo(new MessageMedia(
            'video/gif',
            'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',
            'pixel.gif',
        ));
        $binary = base64_decode($formatted->data, true);

        self::assertIsString($binary);
        self::assertSame('image/webp', $formatted->mimetype);
        self::assertSame('RIFFWEBP', substr($binary, 0, 4).substr($binary, 8, 4));
        $withMetadata = WebpStickerMetadata::apply($binary, 'PAM', 'Community', ['🔥']);
        self::assertStringContainsString('EXIF', $withMetadata);
        self::assertStringContainsString('"sticker-pack-name":"PAM"', $withMetadata);
        self::assertStringContainsString('"emojis":["🔥"]', $withMetadata);
    }

    public function testMediaProducesAnIntegerBackedBridgeEnvelope(): void
    {
        $media = new MessageMedia('image/png', base64_encode('png'), 'image.png', 3);

        self::assertSame([
            'kind' => ContentKind::Media->value,
            'media' => [
                'mimetype' => 'image/png',
                'data' => base64_encode('png'),
                'filename' => 'image.png',
                'filesize' => 3,
            ],
        ], $media->toBridge());
    }

    public function testLocationValidatesBoundsAndBuildsDescription(): void
    {
        $location = new Location(-23.5505, -46.6333, new \Pam\WhatsApp\LocationSendOptions('São Paulo', 'SP'));

        self::assertSame(ContentKind::Location->value, $location->toBridge()['kind']);
        self::assertSame("São Paulo\nSP", $location->description);
    }

    public function testPollRequiresTwoOptionsAndUsesIntegerKind(): void
    {
        $poll = new Poll('Lunch?', ['Pizza', 'Salad'], new \Pam\WhatsApp\PollSendOptions(true));

        self::assertSame(ContentKind::Poll->value, $poll->toBridge()['kind']);
        self::assertTrue($poll->allowMultipleAnswers);
        self::assertSame('Pizza', $poll->pollOptions[0]->name);
        self::assertSame(0, $poll->pollOptions[0]->localId);
    }

    public function testScheduledEventTransmitsIntegerCallType(): void
    {
        $event = new ScheduledEvent(
            'Community call',
            new DateTimeImmutable('2030-01-01T12:00:00Z'),
            new \Pam\WhatsApp\ScheduledEventSendOptions(callType: ScheduledEventCallType::Video),
        );

        self::assertSame(ContentKind::ScheduledEvent->value, $event->toBridge()['kind']);
        self::assertSame(ScheduledEventCallType::Video->value, $event->toBridge()['callType']);
    }

    public function testContactListButtonsAndListUseDedicatedIntegerKinds(): void
    {
        $contacts = new ContactList(['5511000000000@c.us', '5511999999999@c.us']);
        $buttons = new Buttons('Choose', [new Button('Yes', 'yes')]);
        $list = new ListMessage('Menu', 'Open', [['title' => 'Main', 'rows' => []]]);

        self::assertSame(ContentKind::ContactList->value, $contacts->toBridge()['kind']);
        self::assertSame(ContentKind::Buttons->value, $buttons->toBridge()['kind']);
        self::assertSame('yes', $buttons->toBridge()['buttons'][0]['id']);
        self::assertSame('yes', $buttons->buttons[0]->buttonId);
        self::assertSame('Yes', $buttons->buttons[0]->buttonText->displayText);
        self::assertSame(ContentKind::ListMessage->value, $list->toBridge()['kind']);
    }

    public function testPublicUpstreamEnumsUseSequentialIntegerContracts(): void
    {
        self::assertSame(range(1, 38), array_column(MessageType::cases(), 'value'));
        self::assertSame(range(1, 32), array_column(EventType::cases(), 'value'));
        self::assertSame(range(1, 11), array_column(GroupNotificationType::cases(), 'value'));
        self::assertSame(range(1, 3), array_column(ChatType::cases(), 'value'));
    }

    public function testMessageSendOptionsSerializeTheCompleteUpstreamContract(): void
    {
        $options = new MessageSendOptions(
            sendMediaAsSticker: true,
            waitUntilMsgSent: true,
            mentions: ['member@c.us'],
            groupMentions: [new GroupMentionSend('Community', 'community@g.us')],
            invokedBotWid: 'bot@lid',
            media: new MessageMedia('image/png', base64_encode('png')),
            extra: ['custom' => true],
            stickerName: 'PAM',
            stickerAuthor: 'Pushin',
            stickerCategories: ['🤖'],
        );
        $bridge = $options->toBridge();

        self::assertTrue($bridge['waitUntilMsgSent']);
        self::assertSame('community@g.us', $bridge['groupMentions'][0]['id']);
        self::assertSame('image/png', $bridge['media']['mimetype']);
        self::assertSame(['custom' => true], $bridge['extraOptions']);
        self::assertSame(['🤖'], $bridge['stickerCategories']);
    }
}
