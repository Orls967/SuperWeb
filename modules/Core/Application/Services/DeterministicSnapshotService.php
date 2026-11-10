<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\Carbon;
use Modules\Core\Application\Projections\SpineProjectionManager;
use Modules\Core\Domain\Algorithms\Events\ProjectionHashCalculator;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Layanan Pembuatan & Verifikasi Snapshot Deterministik (DeterministicSnapshotService)
 */
class DeterministicSnapshotService
{
    /**
     * @var array<string, array{offset: int, state: array<string, mixed>, hash: string, created_at: Carbon}>
     */
    protected static array $inMemorySnapshots = [];

    public function __construct(
        protected SpineProjectionManager $projectionManager,
        protected ProjectionHashCalculator $hashCalculator
    ) {}

    /**
     * Membuat snapshot keadaan proyeksi hingga offset tertentu.
     *
     * @return array{offset: int, state: array<string, mixed>, hash: string}
     */
    public function takeSnapshot(string $group, int $upToOffset): array
    {
        $events = SimEventSpine::where('id', '<=', $upToOffset)->orderBy('id', 'asc')->get();
        $projection = $this->projectionManager->project($events);

        $snapshot = [
            'offset' => $upToOffset,
            'state' => $projection['state'],
            'hash' => $projection['hash'],
            'created_at' => Carbon::now(),
        ];

        self::$inMemorySnapshots[$group] = $snapshot;

        return [
            'offset' => $upToOffset,
            'state' => $projection['state'],
            'hash' => $projection['hash'],
        ];
    }

    /**
     * Mengambil snapshot terakhir untuk grup konsumen.
     *
     * @return array{offset: int, state: array<string, mixed>, hash: string, created_at: Carbon}|null
     */
    public function getLatestSnapshot(string $group): ?array
    {
        return self::$inMemorySnapshots[$group] ?? null;
    }

    /**
     * Memverifikasi apakah hash snapshot cocok dengan perhitungan ulang kanonikal.
     *
     * @param  array<string, mixed>  $state
     */
    public function verifySnapshotIntegrity(array $state, string $expectedHash): bool
    {
        $computed = $this->hashCalculator->computeHash($state);

        return hash_equals($expectedHash, $computed);
    }
}
