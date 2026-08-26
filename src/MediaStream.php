<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use IteratorAggregate;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;
use Traversable;

/** @implements IteratorAggregate<int, string> */
final class MediaStream implements IteratorAggregate
{
    private int $offset = 0;
    private bool $closed = false;

    public function __construct(
        private readonly Session $session,
        private readonly string $token,
        private readonly int $blobSize,
        private readonly int $chunkSize,
    ) {
    }

    public function read(): ?string
    {
        if ($this->closed || $this->offset >= $this->blobSize) {
            $this->close();

            return null;
        }
        $payload = Payload::object($this->session->invoke('readMessageMediaStream', [
            $this->token,
            $this->offset,
            $this->chunkSize,
        ]), 'Media stream chunk');
        $encoded = Payload::string($payload, 'data');
        $chunk = base64_decode($encoded, true);
        if (!is_string($chunk)) throw new \RuntimeException('Media stream chunk is not valid base64.');
        $this->offset += strlen($chunk);
        if (Payload::bool($payload, 'done')) $this->close();

        return $chunk === '' ? null : $chunk;
    }

    public function close(): void
    {
        if ($this->closed) return;
        $this->closed = true;
        $this->session->invoke('closeMessageMediaStream', [$this->token]);
    }

    public function getIterator(): Traversable
    {
        try {
            while (($chunk = $this->read()) !== null) yield $chunk;
        } finally {
            $this->close();
        }
    }
}
