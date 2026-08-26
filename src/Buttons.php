<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageContent;

final readonly class Buttons implements MessageContent
{
    /** @var list<ButtonSpec> */
    public array $buttons;

    /** @param list<Button> $buttons */
    public function __construct(
        public string|MessageMedia $body,
        array $buttons,
        public ?string $title = null,
        public ?string $footer = null,
    ) {
        if ($body === '' || $buttons === []) {
            throw new \InvalidArgumentException('Buttons require a body and at least one button.');
        }
        $this->buttons = array_map(
            static fn (Button $button, int $index): ButtonSpec => new ButtonSpec(
                $button->id ?? (string) $index,
                new ButtonText($button->body),
                1,
            ),
            $buttons,
            array_keys($buttons),
        );
    }

    public function toBridge(): array
    {
        return [
            'kind' => ContentKind::Buttons->value,
            'body' => is_string($this->body) ? $this->body : '',
            'media' => $this->body instanceof MessageMedia ? $this->body->toBridge()['media'] : null,
            'buttons' => array_map(
                static fn (ButtonSpec $button): array => [
                    'id' => $button->buttonId,
                    'body' => $button->buttonText->displayText,
                ],
                $this->buttons,
            ),
            'title' => $this->title,
            'footer' => $this->footer,
        ];
    }
}
