<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class Button
{
    public function __construct(public string $body, public ?string $id = null)
    {
        if ($body === '' || $id === '') {
            throw new \InvalidArgumentException('Button body and optional id must be non-empty.');
        }
    }

    /** @return array{id?: string, body: string} */
    public function toBridge(): array
    {
        return $this->id === null ? ['body' => $this->body] : ['id' => $this->id, 'body' => $this->body];
    }
}
