<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageSendChannelOptions
{
    /**
     * @param list<string> $mentions
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?string $caption = null,
        public array $mentions = [],
        public ?MessageMedia $media = null,
        public array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toBridge(): array
    {
        $options = $this->extra;
        if ($this->caption !== null) {
            $options['caption'] = $this->caption;
        }
        if ($this->mentions !== []) {
            $options['mentions'] = $this->mentions;
        }
        if ($this->media !== null) {
            $options['media'] = $this->media->toBridge()['media'];
        }

        return $options;
    }
}
