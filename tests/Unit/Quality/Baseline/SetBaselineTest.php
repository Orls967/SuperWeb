<?php

declare(strict_types=1);

use App\Quality\Baseline\SetBaseline;

it('reports entries that are new and entries that disappeared', function (): void {
    $baseline = new SetBaseline(['file:a.php', 'file:b.php']);

    expect($baseline->compare(['file:b.php', 'file:c.php']))->toBe(['new' => ['file:c.php'], 'stale' => ['file:a.php']]);
});

it('lowers the baseline without a decision but refuses to grow it without one', function (): void {
    $baseline = new SetBaseline(['file:a.php', 'file:b.php']);

    expect($baseline->updatedFrom(['file:b.php'], null, '2026-10-10')->entries())->toBe(['file:b.php']);

    $baseline->updatedFrom(['file:b.php', 'file:c.php'], null, '2026-10-10');
})->throws(RuntimeException::class, 'Baseline tidak boleh naik');

it('records approved additions and summarises the initial snapshot', function (): void {
    $initial = (new SetBaseline([]))->updatedFrom(['x', 'y'], 'DECISIONS.md#awal', '2026-10-10');
    $grown = $initial->updatedFrom(['x', 'y', 'z'], 'DECISIONS.md#tambah', '2026-10-11');

    expect($grown->approvedAdditions())->toBe([
        ['date' => '2026-10-10', 'decision' => 'DECISIONS.md#awal', 'entries' => 'baseline awal: 2 entri'],
        ['date' => '2026-10-11', 'decision' => 'DECISIONS.md#tambah', 'entries' => ['z']],
    ])->and($grown->entries())->toBe(['x', 'y', 'z']);
});

it('round-trips through its JSON file', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'set-baseline');
    file_put_contents($path, (new SetBaseline(['b', 'a']))->toJson('about'));

    $loaded = SetBaseline::fromFile($path);

    expect($loaded->entries())->toBe(['a', 'b']);
    unlink($path);
});
