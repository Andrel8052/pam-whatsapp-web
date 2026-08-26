<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageEditOptions
{
    /**
     * @param list<string> $mentions
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public bool $linkPreview = true,
        public array $mentions = [],
        public array $extra = [],
    ) {
    }

    /** @return array{linkPreview: bool, mentionedJidList: list<string>, extraOptions: array<string, mixed>} */
    public function toBridge(): array
    {
        return [
            'linkPreview' => $this->linkPreview,
            'mentionedJidList' => $this->mentions,
            'extraOptions' => $this->extra,
        ];
    }
}
