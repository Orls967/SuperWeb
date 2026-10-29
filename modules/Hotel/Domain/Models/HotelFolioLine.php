<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelFolioLine extends Model
{
    use HasUuids;

    protected $table = 'htl_folio_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'folio_id',
        'business_date',
        'line_type',
        'description',
        'amount_idr',
        'base_amount_idr',
        'service_charge_idr',
        'tax_pbjt_idr',
        'reversal_of_id',
        'idempotency_key',
    ];

    protected $casts = [
        'business_date' => 'date',
        'amount_idr' => 'integer',
        'base_amount_idr' => 'integer',
        'service_charge_idr' => 'integer',
        'tax_pbjt_idr' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Baris folio bersifat append-only dan tidak dapat diubah atau dihapus.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Baris folio bersifat append-only dan tidak dapat diubah atau dihapus.');
        });
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(HotelFolio::class, 'folio_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
