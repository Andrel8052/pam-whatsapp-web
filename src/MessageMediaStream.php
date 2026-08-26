<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageMediaStream implements MessageMediaMetadata
{
    public function __construct(
        public MediaStream $stream,
        public string $mimetype,
        public ?string $filename = null,
        public ?int $filesize = null,
    ) {
    }
}
