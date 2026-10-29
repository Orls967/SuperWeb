<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelCityLedgerAccount extends Model
{
    use HasUuids;

    protected $table = 'htl_city_ledger_accounts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_id',
        'company_name',
        'company_code',
        'credit_limit_idr',
        'outstanding_balance_idr',
        'is_active',
    ];

    protected $casts = [
        'credit_limit_idr' => 'integer',
        'outstanding_balance_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(HotelCityLedgerTransfer::class, 'city_ledger_account_id');
    }

    public function hasSufficientCredit(int $amount): bool
    {
        return ($this->outstanding_balance_idr + $amount) <= $this->credit_limit_idr;
    }
}
