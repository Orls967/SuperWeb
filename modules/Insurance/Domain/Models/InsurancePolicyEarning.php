<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only fact record for daily recognized insurance premium earnings (PR5).
 * Calculated via CumulativeRounding (B0′.2). Rejects updates and deletes.
 */
class InsurancePolicyEarning extends Model
{
    protected $table = 'ins_policy_earnings';

    public $timestamps = false;

    protected $fillable = [
        'policy_id',
        'earning_date',
        'day_index',
        'amount_idr',
        'daily_amount_idr',
        'cumulative_earned_idr',
        'idempotency_key',
        'created_at',
    ];

    protected $casts = [
        'policy_id' => 'integer',
        'earning_date' => 'date',
        'day_index' => 'integer',
        'amount_idr' => 'integer',
        'cumulative_earned_idr' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('InsurancePolicyEarning is an immutable append-only fact and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new DomainException('InsurancePolicyEarning is an immutable append-only fact and cannot be deleted.');
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class, 'policy_id');
    }
}
