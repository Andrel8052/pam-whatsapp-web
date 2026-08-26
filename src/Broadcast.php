<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageData;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\ChatFactory;
use Pam\WhatsApp\Support\Payload;
use Pam\WhatsApp\Support\ContactFactory;

final readonly class Broadcast
{
    /** @var list<Message> */
    public array $msgs;

    /** @param array<string, mixed> $payload */
    public function __construct(private Session $session, array $payload)
    {
        $this->id = ContactId::fromPayload(Payload::object($payload['id'] ?? null, 'Broadcast id'));
        $this->timestamp = Payload::int($payload, 'timestamp');
        $this->totalCount = Payload::int($payload, 'totalCount');
        $this->unreadCount = Payload::int($payload, 'unreadCount');
        $this->msgs = array_map(
            fn (array $message): Message => new Message($session, MessageData::fromPayload($message)),
            Payload::objects($payload['msgs'] ?? [], 'Broadcast messages'),
        );
    }

    public ContactId $id;
    public int $timestamp;
    public int $totalCount;
    public int $unreadCount;

    public function getChat(): Chat
    {
        $chat = ChatFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getChatById', [$this->id->serialized]), 'Broadcast chat'),
        );

        if ($chat instanceof Channel) {
            throw new \UnexpectedValueException('A broadcast chat cannot be a channel.');
        }

        return $chat;
    }

    public function getContact(): Contact
    {
        return ContactFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getContactById', [$this->id->serialized]), 'Broadcast contact'),
        );
    }
}
