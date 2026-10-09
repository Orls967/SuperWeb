<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger pemakaian plafon kontrak — 29.5.
 *
 * Baris immutabel yang dicatat oleh sinkronisator transaksi riil
 * (PO, penjualan, pengiriman, billing sewa, royalti). Unique per
 * `source_type + source_id` sehingga rekonsiliasi idempoten.
 */
class UsageLedger extends Model
{
    protected $table = 'ctr_usage_ledger';

    protected $fillable = [
        'contract_id', 'source_type', 'source_id',
        'amount_idr', 'occurred_at', 'note',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Catat pemakaian plafon secara idempoten.
     *
     * @return bool true bila baris baru dibuat (bukan replay)
     */
    public static function recordOnce(
        string $contractId,
        string $sourceType,
        int $sourceId,
        int $amountIdr,
        ?string $note = null
    ): bool {
        $existing = static::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing !== null) {
            return false;
        }

        static::create([
            'contract_id' => $contractId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount_idr' => $amountIdr,
            'occurred_at' => now(),
            'note' => $note,
        ]);

        return true;
    }
}
