<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests\Unit;

use Pam\WhatsApp\Bridge\BridgeEvent;
use Pam\WhatsApp\Channel;
use Pam\WhatsApp\ChannelReactionSetting;
use Pam\WhatsApp\ChannelSearchView;
use Pam\WhatsApp\ChannelSubscriberRole;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\CreateChannelOptions;
use Pam\WhatsApp\CreateChannelResult;
use Pam\WhatsApp\MessageContentType;
use Pam\WhatsApp\MessageType;
use Pam\WhatsApp\MessageSearchOptions;
use Pam\WhatsApp\SendChannelAdminInviteOptions;
use Pam\WhatsApp\SearchChannelsOptions;
use Pam\WhatsApp\TransferChannelOwnershipOptions;
use Pam\WhatsApp\UnsubscribeOptions;
use PHPUnit\Framework\TestCase;

final class ChannelTest extends TestCase
{
    public function testClientAndFactoryHydrateChannels(): void
    {
        $session = new ChannelFakeSession();
        $client = Client::forSession($session);

        $channels = $client->getChannels();
        $byId = $client->getChatById('news@newsletter');

        self::assertCount(1, $channels);
        self::assertInstanceOf(Channel::class, $byId);
        self::assertSame('PAM News', $channels[0]->name);
        self::assertSame('Community updates', $channels[0]->description);
        self::assertTrue($channels[0]->isChannel);
        self::assertSame('news@newsletter', $session->lastArguments['getChatById'][0]);
    }

    public function testChannelExposesMetadataModerationAndMessageOperations(): void
    {
        $session = new ChannelFakeSession();
        $channel = new Channel($session, $session->payload());

        self::assertTrue($channel->setSubject('New name'));
        self::assertSame('New name', $channel->name);
        self::assertTrue($channel->setDescription('New description'));
        self::assertSame('New description', $channel->description);
        self::assertTrue($channel->setReactionSetting(ChannelReactionSetting::All));
        self::assertSame(ChannelReactionSetting::All->value, $session->lastArguments['setChannelReactionSetting'][1]);
        self::assertTrue($channel->mute());
        self::assertTrue($channel->isMuted);
        self::assertTrue($channel->unmute());
        self::assertFalse($channel->isMuted);
        self::assertTrue($channel->sendChannelAdminInvite('admin@c.us', new SendChannelAdminInviteOptions('Join us')));
        self::assertTrue($channel->acceptChannelAdminInvite());
        self::assertTrue($channel->revokeChannelAdminInvite('admin@c.us'));
        self::assertTrue($channel->demoteChannelAdmin('admin@c.us'));
        self::assertTrue($channel->transferChannelOwnership('owner@c.us', new TransferChannelOwnershipOptions(true)));
        self::assertTrue($channel->sendSeen());
        self::assertTrue($channel->deleteChannel());

        $subscribers = $channel->getSubscribers(10);
        self::assertSame(ChannelSubscriberRole::Admin, $subscribers[0]->role);
        self::assertSame('admin@c.us', $subscribers[0]->contact->id->serialized);
        self::assertSame('channel history', $channel->fetchMessages(new MessageSearchOptions(5))[0]->body);
        self::assertSame('sent', $channel->sendMessage('sent')->body);
    }

    public function testClientExposesTheCompleteChannelSurface(): void
    {
        $session = new ChannelFakeSession();
        $client = Client::forSession($session);

        $created = $client->createChannel('PAM News', new CreateChannelOptions('Updates'));
        self::assertInstanceOf(CreateChannelResult::class, $created);
        self::assertSame('created@newsletter', $created->nid->serialized);
        self::assertSame('https://whatsapp.com/channel/invite', $created->inviteLink);
        self::assertSame('news@newsletter', $client->getChannelByInviteCode('invite')?->id->serialized);
        self::assertSame('newsletter', $client->getChannelByInviteCode('invite')?->id->server);
        self::assertTrue($client->subscribeToChannel('news@newsletter'));
        self::assertTrue($client->unsubscribeFromChannel('news@newsletter', new UnsubscribeOptions(true)));
        self::assertTrue($client->sendChannelAdminInvite('admin@c.us', 'news@newsletter'));
        self::assertTrue($client->acceptChannelAdminInvite('news@newsletter'));
        self::assertTrue($client->revokeChannelAdminInvite('news@newsletter', 'admin@c.us'));
        self::assertTrue($client->demoteChannelAdmin('news@newsletter', 'admin@c.us'));
        self::assertTrue($client->transferChannelOwnership('news@newsletter', 'owner@c.us'));
        self::assertTrue($client->deleteChannel('news@newsletter'));

        $found = $client->searchChannels(new SearchChannelsOptions(
            searchText: ' pam ',
            countryCodes: ['br'],
            skipSubscribedNewsletters: true,
            view: ChannelSearchView::Trending,
            limit: 10,
        ));
        self::assertSame('news@newsletter', $found[0]->id->serialized);
        self::assertSame('pam', $session->lastArguments['searchChannels'][0]['searchText']);
        self::assertSame(['BR'], $session->lastArguments['searchChannels'][0]['countryCodes']);
        self::assertSame(ChannelSearchView::Trending->value, $session->lastArguments['searchChannels'][0]['view']);
    }
}

final class ChannelFakeSession implements Session
{
    /** @var array<string, list<mixed>> */
    public array $lastArguments = [];

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'id' => ['_serialized' => 'news@newsletter'],
            'name' => 'PAM News',
            'description' => 'fallback',
            'channelMetadata' => ['description' => 'Community updates'],
            'isChannel' => true,
            'isGroup' => false,
            'isReadOnly' => false,
            'unreadCount' => 2,
            't' => 1_700_000_000,
            'isMuted' => false,
            'muteExpiration' => 0,
        ];
    }

    public function initialize(callable $listener): void
    {
    }

    public function sendText(string $chatId, string $body, ?string $quotedMessageId = null): MessageData
    {
        return $this->message($body);
    }

    public function sendContent(string $chatId, array $content, array $options = []): MessageData
    {
        return $this->message(is_string($content['text'] ?? null) ? $content['text'] : 'media');
    }

    public function pump(float $timeoutSeconds): bool
    {
        return false;
    }

    public function invoke(string $method, array $arguments = []): mixed
    {
        $this->lastArguments[$method] = $arguments;

        return match ($method) {
            'getChannels' => [$this->payload()],
            'getChatById' => $this->payload(),
            'getChannelByInviteCode' => $this->payload(),
            'searchChannels' => [$this->payload()],
            'createChannel' => [
                'title' => 'PAM News',
                'nid' => ['server' => 'newsletter', 'user' => 'created', '_serialized' => 'created@newsletter'],
                'inviteLink' => 'https://whatsapp.com/channel/invite',
                'createdAtTs' => 1_700_000_002,
            ],
            'getChannelSubscribers' => [[
                'contact' => ['id' => ['_serialized' => 'admin@c.us'], 'name' => 'Admin', 'isUser' => true],
                'role' => ChannelSubscriberRole::Admin->value,
            ]],
            'fetchChannelMessages' => [$this->messagePayload('channel history')],
            default => true,
        };
    }

    public function close(): void
    {
    }

    public function logout(): void
    {
    }

    private function message(string $body): MessageData
    {
        return new MessageData(
            'message-id',
            'news@newsletter',
            'news@newsletter',
            'me@c.us',
            $body,
            false,
            1_700_000_001,
            MessageType::Text,
            MessageContentType::Text,
        );
    }

    /** @return array<string, mixed> */
    private function messagePayload(string $body): array
    {
        return [
            'id' => 'message-id',
            'chatId' => 'news@newsletter',
            'from' => 'news@newsletter',
            'to' => 'me@c.us',
            'body' => $body,
            'fromMe' => false,
            'timestamp' => 1_700_000_001,
            'type' => MessageType::Text->value,
            'contentType' => MessageContentType::Text->value,
        ];
    }
}
