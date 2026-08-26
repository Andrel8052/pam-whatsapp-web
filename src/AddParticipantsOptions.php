<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class AddParticipantsOptions
{
    /** @param int|list<int> $sleep */
    public function __construct(
        public int|array $sleep = [250, 500],
        public bool $autoSendInviteV4 = true,
        public string $comment = '',
    ) {
        $values = is_int($sleep) ? [$sleep] : $sleep;
        if ($values === [] || count($values) > 2) {
            throw new \InvalidArgumentException('Participant sleep must contain one or two values.');
        }
        foreach ($values as $milliseconds) {
            if ($milliseconds < 0) {
                throw new \InvalidArgumentException('Participant sleep cannot be negative.');
            }
        }
    }

    /** @return array{sleep: int|list<int>, autoSendInviteV4: bool, comment: string} */
    public function toBridge(): array
    {
        return [
            'sleep' => $this->sleep,
            'autoSendInviteV4' => $this->autoSendInviteV4,
            'comment' => $this->comment,
        ];
    }
}
