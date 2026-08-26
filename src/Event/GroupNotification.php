<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Event;

use Pam\WhatsApp\Chat;
use Pam\WhatsApp\Channel;
use Pam\WhatsApp\Contact;
use Pam\WhatsApp\ContentKind;
use Pam\WhatsApp\Contract\MessageContent;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\EventType;
use Pam\WhatsApp\GroupNotificationType;
use Pam\WhatsApp\Message;
use Pam\WhatsApp\MessageSendOptions;
use Pam\WhatsApp\Support\ChatFactory;
use Pam\WhatsApp\Support\ContactFactory;
use Pam\WhatsApp\Support\Payload;

final readonly class GroupNotification
{
    /** @param list<string> $recipientIds */
    public function __construct(
        private Session $session,
        public EventType $eventType,
        public GroupNotificationType $type,
        public string $id,
        public string $author,
        public string $body,
        public string $chatId,
        public array $recipientIds,
        public int $timestamp,
    ) {
    }

    public function getChat(): Chat
    {
        $chat = ChatFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getChatById', [$this->chatId]), 'Notification chat'),
        );

        if ($chat instanceof Channel) {
            throw new \UnexpectedValueException('A group notification chat cannot be a channel.');
        }

        return $chat;
    }

    public function getContact(): Contact
    {
        return ContactFactory::make(
            $this->session,
            Payload::object($this->session->invoke('getContactById', [$this->author]), 'Notification contact'),
        );
    }

    /** @return list<Contact> */
    public function getRecipients(): array
    {
        return array_map(
            fn (string $recipientId): Contact => ContactFactory::make(
                $this->session,
                Payload::object($this->session->invoke('getContactById', [$recipientId]), 'Notification recipient'),
            ),
            $this->recipientIds,
        );
    }

    public function reply(string|MessageContent $content, ?MessageSendOptions $options = null): Message
    {
        $payload = is_string($content)
            ? ['kind' => ContentKind::Text->value, 'text' => $content]
            : $content->toBridge();

        return new Message(
            $this->session,
            $this->session->sendContent($this->chatId, $payload, $options?->toBridge() ?? []),
        );
    }
}
