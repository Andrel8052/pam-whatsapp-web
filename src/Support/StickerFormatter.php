<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Support;

use Pam\WhatsApp\MessageMedia;

final readonly class StickerFormatter
{
    public function __construct(private string $ffmpegPath)
    {
        if ($ffmpegPath === '' || str_contains($ffmpegPath, "\0")) {
            throw new \InvalidArgumentException('FFmpeg path is invalid.');
        }
    }

    public function formatVideo(MessageMedia $media): MessageMedia
    {
        if (!str_starts_with($media->mimetype, 'video/')) {
            throw new \InvalidArgumentException('Sticker media is not a video.');
        }
        $binary = base64_decode($media->data, true);
        if (!is_string($binary)) {
            throw new \InvalidArgumentException('Sticker video data is invalid.');
        }
        $input = tempnam(sys_get_temp_dir(), 'pam-sticker-in-');
        if (!is_string($input)) {
            throw new \RuntimeException('Unable to create temporary sticker input.');
        }
        $output = $input.'.webp';
        try {
            if (file_put_contents($input, $binary, LOCK_EX) !== strlen($binary)) {
                throw new \RuntimeException('Unable to write temporary sticker input.');
            }
            $pipes = [];
            $process = @proc_open([
                $this->ffmpegPath,
                '-y',
                '-loglevel', 'error',
                '-i', $input,
                '-vcodec', 'libwebp',
                '-vf', "scale='iw*min(300/iw\\,300/ih)':'ih*min(300/iw\\,300/ih)',format=rgba,pad=300:300:'(300-iw)/2':'(300-ih)/2':'#00000000',setsar=1,fps=10",
                '-loop', '0',
                '-ss', '00:00:00.0',
                '-t', '00:00:05.0',
                '-preset', 'default',
                '-an',
                '-vsync', '0',
                '-s', '512:512',
                $output,
            ], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, options: ['bypass_shell' => true]);
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start FFmpeg sticker conversion.');
            }
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode !== 0 || !is_file($output)) {
                $details = is_string($stderr) && trim($stderr) !== '' ? ': '.trim($stderr) : '';
                throw new \RuntimeException('FFmpeg sticker conversion failed'.$details);
            }
            unset($stdout);
            $webp = file_get_contents($output);
            if (!is_string($webp) || $webp === '') {
                throw new \RuntimeException('FFmpeg produced an empty sticker.');
            }

            return new MessageMedia('image/webp', base64_encode($webp), $media->filename, strlen($webp));
        } finally {
            if (is_file($input)) unlink($input);
            if (is_file($output)) unlink($output);
        }
    }
}
