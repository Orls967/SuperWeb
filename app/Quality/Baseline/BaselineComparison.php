<?php

declare(strict_types=1);

namespace App\Quality\Baseline;

/**
 * Difference between a scan and its ratchet baseline.
 *
 * `new`   — violations not covered by the baseline (the gate fails).
 * `stale` — baseline entries that no longer occur (the gate also fails until the
 *           baseline is lowered in the same change, so the ratchet only goes down).
 */
final class BaselineComparison
{
    /**
     * @param  array<string, list<array{path: string, signature: string, delta: int, lines: list<int>}>>  $new
     * @param  array<string, list<array{path: string, signature: string, delta: int, lines: list<int>}>>  $stale
     */
    public function __construct(
        public readonly array $new,
        public readonly array $stale,
    ) {}

    public function isClean(): bool
    {
        return ! $this->hasNew() && ! $this->hasStale();
    }

    public function hasNew(): bool
    {
        return $this->total($this->new) > 0;
    }

    public function hasStale(): bool
    {
        return $this->total($this->stale) > 0;
    }

    public function newCount(string $ruleId): int
    {
        return array_sum(array_column($this->new[$ruleId] ?? [], 'delta'));
    }

    public function staleCount(string $ruleId): int
    {
        return array_sum(array_column($this->stale[$ruleId] ?? [], 'delta'));
    }

    /**
     * Human-readable list for test failures and command output.
     */
    public function describe(int $limitPerRule = 15): string
    {
        $lines = [];

        foreach (['new' => 'BARU (tidak ada di baseline)', 'stale' => 'BASI (sudah hilang — turunkan baseline)'] as $kind => $title) {
            foreach ($this->{$kind} as $ruleId => $items) {
                if ($items === []) {
                    continue;
                }

                $lines[] = "[{$ruleId}] {$title}: ".array_sum(array_column($items, 'delta'));

                foreach (array_slice($items, 0, $limitPerRule) as $item) {
                    $where = $item['lines'] === [] ? $item['path'] : $item['path'].':'.implode(',', $item['lines']);
                    $lines[] = "  - {$where} :: {$item['signature']} (×{$item['delta']})";
                }

                if (count($items) > $limitPerRule) {
                    $lines[] = '  … '.(count($items) - $limitPerRule).' lokasi lain';
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, list<array{path: string, signature: string, delta: int, lines: list<int>}>>  $items
     */
    private function total(array $items): int
    {
        $total = 0;

        foreach ($items as $ruleItems) {
            $total += array_sum(array_column($ruleItems, 'delta'));
        }

        return $total;
    }
}
