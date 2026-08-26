<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageContent;

final readonly class ListMessage implements MessageContent
{
    /** @param list<array<string, mixed>> $sections */
    public function __construct(
        public string $body,
        public string $buttonText,
        public array $sections,
        public ?string $title = null,
        public ?string $footer = null,
    ) {
        if ($body === '' || $buttonText === '' || $sections === []) {
            throw new \InvalidArgumentException('List messages require body, button text, and sections.');
        }
    }

    public function toBridge(): array
    {
        return [
            'kind' => ContentKind::ListMessage->value,
            'body' => $this->body,
            'buttonText' => $this->buttonText,
            'sections' => $this->sections,
            'title' => $this->title,
            'footer' => $this->footer,
        ];
    }
}
