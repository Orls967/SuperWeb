<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PpaContract extends Model
{
    use HasUuids;

    protected $table = 'egy_ppa_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_number',
        'producer_entity_id',
        'grid_offtaker_id',
        'generator_party_id',
        'offtaker_party_id',
        'capacity_kw',
        'rate_per_kwh_idr',
        'feed_in_tariff_per_kwh_minor',
        'surplus_kwh_exported',
        'total_settlement_minor',
        'valid_from',
        'valid_to',
        'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'capacity_kw' => 'integer',
        'rate_per_kwh_idr' => 'integer',
        'feed_in_tariff_per_kwh_minor' => 'float',
        'surplus_kwh_exported' => 'float',
        'total_settlement_minor' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->producer_entity_id)) {
                $model->producer_entity_id = $model->generator_party_id ?? 'GEN-ENTITY-01';
            }
            if (empty($model->grid_offtaker_id)) {
                $model->grid_offtaker_id = $model->offtaker_party_id ?? 'GRID-OFFTAKER-01';
            }
            if (empty($model->feed_in_tariff_per_kwh_minor)) {
                $model->feed_in_tariff_per_kwh_minor = (float) (($model->rate_per_kwh_idr ?? 1000) * 100);
            }
            if (empty($model->generator_party_id)) {
                $model->generator_party_id = $model->producer_entity_id;
            }
            if (empty($model->offtaker_party_id)) {
                $model->offtaker_party_id = $model->grid_offtaker_id;
            }
            if (empty($model->status)) {
                $model->status = 'active';
            }
        });
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(PpaSettlement::class, 'ppa_contract_id');
    }
}
