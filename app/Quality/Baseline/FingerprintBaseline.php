<?php

declare(strict_types=1);

namespace App\Quality\Baseline;

use App\Quality\ArchScan\ScanReport;
use JsonException;
use RuntimeException;

/**
 * Ratchet baseline of known violations (PROGRESS.md §P11).
 *
 * Violations are stored per rule as `path => signature => count`, so a fix in one
 * place cannot hide a new violation elsewhere, and line shifts do not matter.
 * The baseline may only shrink; adding entries requires a DECISIONS.md reference,
 * which is recorded in `approved_additions` for the verifier.
 */
final class FingerprintBaseline
{
    /**
     * @param  array<string, array<string, array<string, int>>>  $entries  rule => path => signature => count
     * @param  list<array{date: string, decision: string, rule: string, path: string, signature: string, added: int}>  $approvedAdditions
     */
    public function __construct(
        private readonly array $entries,
        private readonly array $approvedAdditions = [],
    ) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            return new self([]);
        }

        try {
            /** @var array{rules?: array<string, array{entries?: array<string, array<string, int>>}>, approved_additions?: list<array{date: string, decision: string, rule: string, path: string, signature: string, added: int}>} $data */
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Baseline {$path} bukan JSON yang valid: {$exception->getMessage()}", previous: $exception);
        }

        $entries = [];

        foreach ($data['rules'] ?? [] as $ruleId => $rule) {
            $entries[(string) $ruleId] = $rule['entries'] ?? [];
        }

        return new self($entries, $data['approved_additions'] ?? []);
    }

    public static function fromReport(ScanReport $report): self
    {
        return new self(self::countsOf($report));
    }

    /**
     * @return list<array{date: string, decision: string, rule: string, path: string, signature: string, added: int}>
     */
    public function approvedAdditions(): array
    {
        return $this->approvedAdditions;
    }

    public function total(string $ruleId): int
    {
        $total = 0;

        foreach ($this->entries[$ruleId] ?? [] as $signatures) {
            $total += array_sum($signatures);
        }

        return $total;
    }

    public function compare(ScanReport $report): BaselineComparison
    {
        $current = self::countsOf($report);
        $new = [];
        $stale = [];

        foreach (array_unique([...array_keys($current), ...array_keys($this->entries)]) as $ruleId) {
            $new[$ruleId] = [];
            $stale[$ruleId] = [];
            $paths = array_unique([...array_keys($current[$ruleId] ?? []), ...array_keys($this->entries[$ruleId] ?? [])]);
            sort($paths);

            foreach ($paths as $path) {
                $currentSignatures = $current[$ruleId][$path] ?? [];
                $baselineSignatures = $this->entries[$ruleId][$path] ?? [];
                $signatures = array_unique([...array_keys($currentSignatures), ...array_keys($baselineSignatures)]);
                sort($signatures);

                foreach ($signatures as $signature) {
                    $delta = ($currentSignatures[$signature] ?? 0) - ($baselineSignatures[$signature] ?? 0);

                    if ($delta > 0) {
                        $new[$ruleId][] = ['path' => $path, 'signature' => $signature, 'delta' => $delta, 'lines' => self::lines($report, $ruleId, $path, $signature)];
                    } elseif ($delta < 0) {
                        $stale[$ruleId][] = ['path' => $path, 'signature' => $signature, 'delta' => -$delta, 'lines' => []];
                    }
                }
            }
        }

        return new BaselineComparison($new, $stale);
    }

    /**
     * New baseline equal to the current scan. New violations are refused unless a
     * DECISIONS.md reference is given; the reference is recorded per added entry.
     */
    public function updatedFrom(ScanReport $report, ?string $decision, string $date): self
    {
        $comparison = $this->compare($report);

        if ($comparison->hasNew() && ($decision === null || trim($decision) === '')) {
            throw new BaselineIncreaseRefused($comparison);
        }

        $approved = $this->approvedAdditions;

        if ($this->entries === []) {
            // First snapshot of existing violations: one summary entry instead of thousands.
            $total = 0;

            foreach ($comparison->new as $items) {
                $total += array_sum(array_column($items, 'delta'));
            }

            $approved[] = ['date' => $date, 'decision' => (string) $decision, 'rule' => '*', 'path' => '*', 'signature' => 'baseline awal', 'added' => $total];

            return new self(self::countsOf($report), $approved);
        }

        foreach ($comparison->new as $ruleId => $items) {
            foreach ($items as $item) {
                $approved[] = [
                    'date' => $date,
                    'decision' => (string) $decision,
                    'rule' => $ruleId,
                    'path' => $item['path'],
                    'signature' => $item['signature'],
                    'added' => $item['delta'],
                ];
            }
        }

        return new self(self::countsOf($report), $approved);
    }

    public function toJson(string $about): string
    {
        $rules = [];
        $ruleIds = array_keys($this->entries);
        natsort($ruleIds);

        foreach ($ruleIds as $ruleId) {
            $entries = $this->entries[$ruleId];
            ksort($entries);

            foreach ($entries as $path => $signatures) {
                ksort($signatures);
                $entries[$path] = $signatures;
            }

            $rules[$ruleId] = ['total' => $this->total($ruleId), 'entries' => (object) $entries];
        }

        return json_encode([
            'about' => $about,
            'rules' => (object) $rules,
            'approved_additions' => $this->approvedAdditions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    private static function countsOf(ScanReport $report): array
    {
        $counts = [];

        foreach ($report->ruleIds() as $ruleId) {
            $counts[$ruleId] = [];

            foreach ($report->violations($ruleId) as $violation) {
                $counts[$ruleId][$violation->path][$violation->signature] = ($counts[$ruleId][$violation->path][$violation->signature] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return list<int>
     */
    private static function lines(ScanReport $report, string $ruleId, string $path, string $signature): array
    {
        $lines = [];

        foreach ($report->violations($ruleId) as $violation) {
            if ($violation->path === $path && $violation->signature === $signature) {
                $lines[] = $violation->line;
            }
        }

        return $lines;
    }
}
