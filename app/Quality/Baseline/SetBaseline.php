<?php

declare(strict_types=1);

namespace App\Quality\Baseline;

use JsonException;
use RuntimeException;

/**
 * Ratchet baseline for a set of known exceptions (files, tables, commands, account
 * codes, routes, …). Same rules as FingerprintBaseline: entries may only be removed;
 * additions need a DECISIONS.md reference that is recorded in `approved_additions`.
 */
final class SetBaseline
{
    /** @var list<string> */
    private array $entries;

    /**
     * @param  list<string>  $entries
     * @param  list<array{date: string, decision: string, entries: list<string>|string}>  $approvedAdditions
     */
    public function __construct(array $entries, private readonly array $approvedAdditions = [])
    {
        $entries = array_values(array_unique($entries));
        sort($entries);
        $this->entries = $entries;
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            return new self([]);
        }

        try {
            /** @var array{entries?: list<string>, approved_additions?: list<array{date: string, decision: string, entries: list<string>|string}>} $data */
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Baseline {$path} bukan JSON yang valid: {$exception->getMessage()}", previous: $exception);
        }

        return new self($data['entries'] ?? [], $data['approved_additions'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<array{date: string, decision: string, entries: list<string>|string}>
     */
    public function approvedAdditions(): array
    {
        return $this->approvedAdditions;
    }

    /**
     * @param  list<string>  $current
     * @return array{new: list<string>, stale: list<string>}
     */
    public function compare(array $current): array
    {
        $current = array_values(array_unique($current));

        return [
            'new' => array_values(array_diff($current, $this->entries)),
            'stale' => array_values(array_diff($this->entries, $current)),
        ];
    }

    /**
     * @param  list<string>  $current
     */
    public function updatedFrom(array $current, ?string $decision, string $date): self
    {
        $new = $this->compare($current)['new'];

        if ($new === []) {
            return new self($current, $this->approvedAdditions);
        }

        if ($decision === null || trim($decision) === '') {
            throw new RuntimeException("Baseline tidak boleh naik tanpa rujukan DECISIONS.md. Entri baru:\n- ".implode("\n- ", $new));
        }

        $approved = $this->approvedAdditions;
        $approved[] = [
            'date' => $date,
            'decision' => $decision,
            'entries' => $this->entries === [] ? 'baseline awal: '.count($new).' entri' : $new,
        ];

        return new self($current, $approved);
    }

    public function toJson(string $about): string
    {
        return json_encode([
            'about' => $about,
            'total' => count($this->entries),
            'entries' => $this->entries,
            'approved_additions' => $this->approvedAdditions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }
}
