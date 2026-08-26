<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageSearchQueryOptions
{
    public function __construct(
        public ?string $chatId = null,
        public int $page = 1,
        public int $limit = 10,
    ) {
        if ($page < 1 || $limit < 1) {
            throw new \InvalidArgumentException('Message search page and limit must be positive.');
        }
    }

    /** @return array{chatId: ?string, page: int, limit: int} */
    public function toBridge(): array
    {
        return ['chatId' => $this->chatId, 'page' => $this->page, 'limit' => $this->limit];
    }
}
