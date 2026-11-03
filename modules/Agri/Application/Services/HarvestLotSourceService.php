<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Agri\Contracts\HarvestLotSource;
use Modules\Agri\Domain\Models\AgriCollectionBatch;

class HarvestLotSourceService implements HarvestLotSource
{
    /**
     * @return array<string, mixed>|null
     */
    public function describe(string|int $collectionBatchId): ?array
    {
        $batch = AgriCollectionBatch::with('contract')->find((string) $collectionBatchId);
        if ($batch === null) {
            return null;
        }

        $grossGrams = (int) round(((float) $batch->gross_weight_kg) * 1000);
        $availableGrams = $this->availableGrams($collectionBatchId);

        return [
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'commodity' => $batch->contract?->commodity_type ?? 'KOMODITAS',
            'grade' => $batch->grade,
            'moisture_percentage' => (float) $batch->moisture_percentage,
            'gross_weight_grams' => $grossGrams,
            'available_grams' => $availableGrams,
            'price_per_kg' => (int) $batch->buying_price_per_kg,
        ];
    }

    public function availableGrams(string|int $collectionBatchId): int
    {
        $batch = AgriCollectionBatch::find((string) $collectionBatchId);
        if ($batch === null) {
            return 0;
        }

        $grossGrams = (int) round(((float) $batch->gross_weight_kg) * 1000);
        $consumed = (int) DB::table('agri_collection_consumptions')
            ->where('collection_batch_id', (string) $collectionBatchId)
            ->sum('grams_consumed');

        return max(0, $grossGrams - $consumed);
    }

    public function consume(string|int $collectionBatchId, int $grams, string $idempotencyKey): void
    {
        DB::transaction(function () use ($collectionBatchId, $grams, $idempotencyKey) {
            $existing = DB::table('agri_collection_consumptions')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return;
            }

            DB::table('agri_collection_consumptions')->insert([
                'collection_batch_id' => (string) $collectionBatchId,
                'grams_consumed' => $grams,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
