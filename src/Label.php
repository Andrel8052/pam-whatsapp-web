<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;
use Pam\WhatsApp\Support\ChatFactory;

final readonly class Label
{
    public string $id;
    public string $name;
    public string $hexColor;

    /** @param array<string, mixed> $payload */
    public function __construct(private Session $session, array $payload)
    {
        $this->id = Payload::string($payload, 'id');
        $this->name = Payload::string($payload, 'name');
        $this->hexColor = Payload::string($payload, 'hexColor');
    }

    /** @return list<Chat> */
    public function getChats(): array
    {
        return array_map(
            function (array $payload): Chat {
                $chat = ChatFactory::make($this->session, $payload);
                if ($chat instanceof Channel) {
                    throw new \UnexpectedValueException('A label chat collection cannot contain a channel.');
                }

                return $chat;
            },
            Payload::objects($this->session->invoke('getChatsByLabelId', [$this->id]), 'Label chats'),
        );
    }
}
