<?php

declare(strict_types=1);

enum CertificationStatus: int
{
    case Passed = 1;
    case Failed = 2;
    case Skipped = 3;
}

final class CertificationReport
{
    /** @var list<array{name: string, status: int, durationMs: int, covers: list<string>, detail?: string}> */
    private array $checks = [];

    /** @param array<string, true> $knownEntries */
    public function __construct(private readonly array $knownEntries) {}

    /** @param list<string> $covers */
    public function check(string $name, array $covers, Closure $operation): void
    {
        foreach ($covers as $entry) {
            if (!isset($this->knownEntries[$entry])) {
                throw new InvalidArgumentException("Unknown certification matrix entry: {$entry}");
            }
        }
        $started = hrtime(true);
        try {
            $operation();
            $this->checks[] = [
                'name' => $name,
                'status' => CertificationStatus::Passed->value,
                'durationMs' => (int) ((hrtime(true) - $started) / 1_000_000),
                'covers' => $covers,
            ];
        } catch (Throwable $exception) {
            $this->checks[] = [
                'name' => $name,
                'status' => CertificationStatus::Failed->value,
                'durationMs' => (int) ((hrtime(true) - $started) / 1_000_000),
                'covers' => [],
                'detail' => $exception::class.': '.$exception->getMessage(),
            ];
        }
    }

    public function skip(string $name, string $detail): void
    {
        $this->checks[] = [
            'name' => $name,
            'status' => CertificationStatus::Skipped->value,
            'durationMs' => 0,
            'covers' => [],
            'detail' => $detail,
        ];
    }

    public function failed(): bool
    {
        return array_any(
            $this->checks,
            static fn (array $check): bool => $check['status'] === CertificationStatus::Failed->value,
        );
    }

    /** @return array<string, mixed> */
    public function payload(string $webVersion, bool $mutationsEnabled): array
    {
        return [
            'schemaVersion' => 2,
            'baseline' => [
                'package' => 'whatsapp-web.js',
                'version' => '1.34.7',
                'commit' => '942d236a11ad68807308b058303ba5256915979c',
            ],
            'runtime' => [
                'php' => PHP_VERSION,
                'whatsappWeb' => $webVersion,
                'mutationsEnabled' => $mutationsEnabled,
                'completedAt' => gmdate(DATE_ATOM),
            ],
            'summary' => [
                'passed' => count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 1)),
                'failed' => count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 2)),
                'skipped' => count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 3)),
            ],
            'checks' => $this->checks,
        ];
    }
}
