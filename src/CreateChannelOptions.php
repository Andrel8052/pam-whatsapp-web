<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class CreateChannelOptions
{
    public function __construct(
        public ?string $description = null,
        public ?MessageMedia $picture = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toBridge(): array
    {
        $options = [];
        if ($this->description !== null) {
            $options['description'] = $this->description;
        }
        if ($this->picture !== null) {
            $options['picture'] = $this->picture->toBridge()['media'];
        }

        return $options;
    }
}
