<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageSearchOptions
{
    public function __construct(public ?int $limit = null, public ?bool $fromMe = null)
    {
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('Message search limit must be positive.');
        }
    }

    /** @return array{limit?: int, fromMe?: bool} */
    public function toBridge(): array
    {
        $options = [];
        if ($this->limit !== null) {
            $options['limit'] = $this->limit;
        }
        if ($this->fromMe !== null) {
            $options['fromMe'] = $this->fromMe;
        }

        return $options;
    }
}
