<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MiningOfftakeContract extends Model
{
    use HasUuids;

    protected $table = 'min_offtake_contracts';

    protected $fillable = [
        'id',
        'site_id',
        'contract_number',
        'contract_code',
        'buyer_party_ref',
        'party_ref',
        'commodity',
        'destination_type',
        'pricing_formula',
        'base_price_cents',
        'base_price_usd_cents',
        'target_volume_tons',
        'contracted_tonnage_kg',
        'fulfilled_volume_tons',
        'benchmark_gar_kcal',
        'lc_required',
        'status',
    ];

    protected $casts = [
        'party_ref' => 'integer',
        'base_price_cents' => 'integer',
        'base_price_usd_cents' => 'integer',
        'target_volume_tons' => 'integer',
        'contracted_tonnage_kg' => 'integer',
        'fulfilled_volume_tons' => 'integer',
        'benchmark_gar_kcal' => 'integer',
        'lc_required' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->contract_number)) {
                $model->contract_number = $model->contract_code ?? ('CNT-'.strtoupper(Str::random(8)));
            }
            if (empty($model->contract_code)) {
                $model->contract_code = $model->contract_number;
            }
        });
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(MiningOfftakeShipment::class, 'contract_id');
    }

    public function remainingVolumeTons(): int
    {
        return (int) max(0, $this->target_volume_tons - $this->fulfilled_volume_tons);
    }
}
