<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelCityLedgerTransfer extends Model
{
    use HasUuids;

    protected $table = 'htl_city_ledger_transfers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'city_ledger_account_id',
        'folio_id',
        'transferred_amount_idr',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'transferred_amount_idr' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(HotelCityLedgerAccount::class, 'city_ledger_account_id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(HotelFolio::class, 'folio_id');
    }
}
