<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EscoContract extends Model
{
    protected $table = 'egy_esco_contracts';

    protected $fillable = [
        'contract_number',
        'facility_name',
        'client_party_id',
        'baseline_monthly_kwh',
        'baseline_tariff_idr',
        'esco_share_bps',
        'guaranteed_savings_kwh',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'client_party_id' => 'integer',
        'baseline_monthly_kwh' => 'integer',
        'baseline_tariff_idr' => 'integer',
        'esco_share_bps' => 'integer',
        'guaranteed_savings_kwh' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function settlements(): HasMany
    {
        return $this->hasMany(EscoSettlement::class, 'esco_contract_id');
    }
}
