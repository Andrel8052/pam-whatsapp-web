<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

interface MessageMediaMetadata
{
    public string $mimetype { get; }
    public ?string $filename { get; }
    public ?int $filesize { get; }
}
