<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetLedgerEntry extends Model
{
    protected $table = 'prt_fleet_ledger_entries';

    protected $fillable = [
        'entry_code',
        'voyage_id',
        'vessel_id',
        'account_code',
        'entry_type',
        'amount',
        'currency',
        'reference_type',
        'reference_id',
        'description',
        'checksum',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(FleetVoyage::class, 'voyage_id');
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }

    public static function generateChecksum(
        string $code,
        string $account,
        string $type,
        float $amount,
        string $currency,
        string $refType,
        int $refId
    ): string {
        return hash('sha256', "{$code}|{$account}|{$type}|".number_format($amount, 2, '.', '')."|{$currency}|{$refType}|{$refId}");
    }

    public function verifyChecksum(): bool
    {
        $expected = self::generateChecksum(
            $this->entry_code,
            $this->account_code,
            $this->entry_type,
            (float) $this->amount,
            $this->currency,
            $this->reference_type,
            (int) $this->reference_id
        );

        return hash_equals($expected, $this->checksum);
    }
}
