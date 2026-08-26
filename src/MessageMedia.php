<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageContent;

final readonly class MessageMedia implements MessageContent, MessageMediaMetadata
{
    public function __construct(
        public string $mimetype,
        public string $data,
        public ?string $filename = null,
        public ?int $filesize = null,
    ) {
        if ($mimetype === '' || str_contains($mimetype, "\0")) {
            throw new \InvalidArgumentException('Media MIME type is invalid.');
        }
        if ($data === '' || base64_decode($data, true) === false) {
            throw new \InvalidArgumentException('Media data must be valid non-empty base64.');
        }
        if ($filesize !== null && $filesize < 0) {
            throw new \InvalidArgumentException('Media file size cannot be negative.');
        }
    }

    public static function fromFilePath(string $filePath): self
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException('Media file is not readable.');
        }
        $data = file_get_contents($filePath);
        if (!is_string($data)) {
            throw new \RuntimeException('Unable to read media file.');
        }
        $mime = self::mimeFromExtension(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($mime === null && class_exists(\finfo::class)) {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($filePath);
            $mime = is_string($detected) ? $detected : null;
        }
        $size = filesize($filePath);

        return new self(
            $mime ?? 'application/octet-stream',
            base64_encode($data),
            basename($filePath),
            is_int($size) ? $size : strlen($data),
        );
    }

    public static function fromUrl(string $url, ?MediaFromURLOptions $options = null): self
    {
        $options ??= new MediaFromURLOptions();
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https', 'data'], true)) {
            throw new \InvalidArgumentException('Media URL must use HTTP, HTTPS, or data scheme.');
        }
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';
        $extensionMime = self::mimeFromExtension(pathinfo($path, PATHINFO_EXTENSION));
        if ($extensionMime === null && !$options->unsafeMime) {
            throw new \RuntimeException(
                'Unable to determine MIME type using URL. Set unsafeMime to true to download it anyway.',
            );
        }
        if ($options->client !== null) {
            return self::fromBrowserUrl($url, $path, $extensionMime, $options);
        }

        $request = $options->reqOptions ?? [];
        $maximumBytes = $request['size'] ?? 0;
        if (!is_int($maximumBytes) || $maximumBytes < 0) {
            throw new \InvalidArgumentException('Media request size must be a non-negative integer.');
        }
        $headers = $request['headers'] ?? ['Accept' => 'image/*, video/*, text/*, audio/*'];
        if (!is_array($headers)) {
            throw new \InvalidArgumentException('Media request headers must be an array.');
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value) || preg_match('/^[A-Za-z0-9-]+$/', $name) !== 1
                || str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new \InvalidArgumentException('Media request headers are invalid.');
            }
            $headerLines[] = $name.': '.$value;
        }
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines),
            'timeout' => 30.0,
            'follow_location' => 1,
            'max_redirects' => 10,
            'ignore_errors' => false,
        ]]);
        $stream = @fopen($url, 'rb', false, $context);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to download media URL.');
        }
        try {
            $data = '';
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if (!is_string($chunk)) throw new \RuntimeException('Unable to read media response.');
                $data .= $chunk;
                if ($maximumBytes > 0 && strlen($data) > $maximumBytes) {
                    throw new \RuntimeException('Media response exceeds the configured size limit.');
                }
            }
            $metadata = stream_get_meta_data($stream);
        } finally {
            fclose($stream);
        }
        $responseHeaders = $metadata['wrapper_data'] ?? [];
        $responseHeaders = is_array($responseHeaders) ? $responseHeaders : [];
        $contentType = self::responseHeader($responseHeaders, 'Content-Type');
        $contentType = $contentType === null ? null : trim(explode(';', $contentType, 2)[0]);
        $disposition = self::responseHeader($responseHeaders, 'Content-Disposition');
        $responseFilename = null;
        if ($disposition !== null && preg_match('/filename="([^"]+)"/i', $disposition, $match) === 1) {
            $responseFilename = basename($match[1]);
        }
        $filename = $options->filename ?? $responseFilename ?? basename($path ?: 'file');
        $mime = $extensionMime ?? ($contentType !== '' ? $contentType : null);
        if ($mime === null && strtolower($scheme) === 'data'
            && preg_match('/^data:([^;,]+)/i', $url, $matches) === 1
        ) {
            $mime = strtolower($matches[1]);
        }
        if ($mime === null && class_exists(\finfo::class)) {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
            $mime = is_string($detected) ? $detected : null;
        }

        return new self($mime ?? 'application/octet-stream', base64_encode($data), $filename === '' ? 'file' : $filename, strlen($data));
    }

    public function toBridge(): array
    {
        return [
            'kind' => ContentKind::Media->value,
            'media' => [
                'mimetype' => $this->mimetype,
                'data' => $this->data,
                'filename' => $this->filename,
                'filesize' => $this->filesize,
            ],
        ];
    }

    private static function mimeFromExtension(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'ogg', 'oga', 'opus' => 'audio/ogg',
            'wav' => 'audio/wav',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            default => null,
        };
    }

    private static function fromBrowserUrl(
        string $url,
        string $path,
        ?string $extensionMime,
        MediaFromURLOptions $options,
    ): self {
        $page = $options->client?->pupPage;
        if ($page === null) {
            throw new \LogicException('Media URL client must be initialized before browser-side download.');
        }
        $request = $options->reqOptions ?? [];
        $maximumBytes = $request['size'] ?? 0;
        if (!is_int($maximumBytes) || $maximumBytes < 0) {
            throw new \InvalidArgumentException('Media request size must be a non-negative integer.');
        }
        unset($request['size']);
        try {
            $arguments = json_encode([$url, $request], JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Media browser request options must be JSON-compatible.', previous: $exception);
        }
        $value = $page->evaluate(<<<JAVASCRIPT
(async ([url, requestOptions]) => {
    const options = { headers: { accept: 'image/*, video/*, text/*, audio/*' }, ...requestOptions };
    const response = await fetch(url, options);
    if (!response.ok) throw new Error('Media download failed with HTTP ' + response.status + '.');
    const bytes = new Uint8Array(await response.arrayBuffer());
    let binary = '';
    for (let index = 0; index < bytes.length; index += 0x8000) {
        binary += String.fromCharCode(...bytes.subarray(index, index + 0x8000));
    }
    const disposition = response.headers.get('Content-Disposition');
    const match = disposition?.match(/filename="([^"]+)"/i);
    return {
        data: btoa(binary),
        mime: response.headers.get('Content-Type'),
        filename: match?.[1] ?? null,
        size: bytes.length,
    };
})({$arguments})
JAVASCRIPT);
        if (!is_array($value)
            || !is_string($value['data'] ?? null)
            || !is_int($value['size'] ?? null)
        ) {
            throw new \RuntimeException('Browser returned invalid media response data.');
        }
        if ($maximumBytes > 0 && $value['size'] > $maximumBytes) {
            throw new \RuntimeException('Media response exceeds the configured size limit.');
        }
        $responseMime = is_string($value['mime'] ?? null) ? trim(explode(';', $value['mime'], 2)[0]) : null;
        $mime = $extensionMime ?? ($responseMime !== '' ? $responseMime : null) ?? 'application/octet-stream';
        $responseFilename = is_string($value['filename'] ?? null) ? basename($value['filename']) : null;
        $filename = $options->filename ?? $responseFilename ?? basename($path ?: 'file');

        return new self($mime, $value['data'], $filename === '' ? 'file' : $filename, $value['size']);
    }

    /** @param array<mixed> $headers */
    private static function responseHeader(array $headers, string $name): ?string
    {
        foreach (array_reverse($headers) as $header) {
            if (is_string($header) && str_starts_with(strtolower($header), strtolower($name).':')) {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }
}
