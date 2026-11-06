<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class RecCertificate extends Model
{
    use HasFactory;

    protected $table = 'egy_rec_certificates';

    protected $fillable = [
        'certificate_serial',
        'generation_asset_id',
        'energy_source_type',
        'period_month',
        'vintage_year',
        'energy_wh',
        'owner_party_id',
        'status',
        'trading_price_minor',
        'retired_at',
        'retirement_beneficiary',
        'retirement_reason',
        'certificate_hash',
    ];

    protected $casts = [
        'generation_asset_id' => 'integer',
        'energy_wh' => 'integer',
        'owner_party_id' => 'integer',
        'trading_price_minor' => 'integer',
        'retired_at' => 'datetime',
    ];
}
