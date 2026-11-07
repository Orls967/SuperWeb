<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Enums\RoyaltyReturnStatus;

class MiningRoyaltyReturn extends Model
{
    use HasUuids;

    protected $table = 'min_royalty_returns';

    protected $fillable = [
        'id',
        'return_uuid',
        'return_number',
        'site_id',
        'commodity',
        'period_month',
        'period_local',
        'total_production_kg',
        'verified_volume_tons',
        'benchmark_price_cents',
        'benchmark_price_usd_cents_per_ton',
        'exchange_rate_idr',
        'exchange_rate_idr_per_usd_1e6',
        'calculated_royalty_usd_cents',
        'calculated_royalty_idr',
        'finalized_royalty_idr',
        'delta_royalty_idr',
        'rate_bps',
        'royalty_rate_bps',
        'status',
        'document_number',
        'payment_ref',
    ];

    protected $casts = [
        'verified_volume_tons' => 'integer',
        'benchmark_price_cents' => 'integer',
        'exchange_rate_idr' => 'integer',
        'calculated_royalty_usd_cents' => 'integer',
        'calculated_royalty_idr' => 'integer',
        'finalized_royalty_idr' => 'integer',
        'delta_royalty_idr' => 'integer',
        'rate_bps' => 'integer',
        'royalty_rate_bps' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->return_uuid)) {
                $model->return_uuid = $model->return_number ?? (string) Str::uuid();
            }
            if (empty($model->return_number)) {
                $model->return_number = $model->return_uuid;
            }
            if (empty($model->period_local)) {
                $model->period_local = $model->period_month ?? date('Y-m');
            }
            if (empty($model->period_month)) {
                $model->period_month = $model->period_local;
            }
            if (empty($model->commodity)) {
                $model->commodity = 'COAL';
            }
            if (isset($model->rate_bps) && ! isset($model->royalty_rate_bps)) {
                $model->royalty_rate_bps = $model->rate_bps;
            }
            if (isset($model->verified_volume_tons) && ! isset($model->total_production_kg)) {
                $model->total_production_kg = $model->verified_volume_tons * 1000;
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function statusEnum(): RoyaltyReturnStatus
    {
        if ($this->status instanceof RoyaltyReturnStatus) {
            return $this->status;
        }

        $val = strtolower((string) ($this->attributes['status'] ?? 'draft'));

        return RoyaltyReturnStatus::tryFrom($val) ?? RoyaltyReturnStatus::DRAFT;
    }

    public function isSettled(): bool
    {
        $status = $this->statusEnum();

        return $status === RoyaltyReturnStatus::PAID || $status === RoyaltyReturnStatus::CLOSED || strtoupper((string) ($this->attributes['status'] ?? '')) === 'FINALIZED';
    }
}
