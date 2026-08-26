<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class CreateGroupOptions
{
    public function __construct(
        public int $messageTimer = 0,
        public ?string $parentGroupId = null,
        public bool $autoSendInviteV4 = true,
        public string $comment = '',
        public bool $memberAddMode = false,
        public bool $membershipApprovalMode = false,
        public bool $isRestrict = true,
        public bool $isAnnounce = false,
    ) {
        if ($messageTimer < 0) {
            throw new \InvalidArgumentException('Group message timer cannot be negative.');
        }
    }

    /** @return array<string, mixed> */
    public function toBridge(): array
    {
        return [
            'messageTimer' => $this->messageTimer,
            'parentGroupId' => $this->parentGroupId,
            'autoSendInviteV4' => $this->autoSendInviteV4,
            'comment' => $this->comment,
            'memberAddMode' => $this->memberAddMode,
            'membershipApprovalMode' => $this->membershipApprovalMode,
            'isRestrict' => $this->isRestrict,
            'isAnnounce' => $this->isAnnounce,
        ];
    }
}
