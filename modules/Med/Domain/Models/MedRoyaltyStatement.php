<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedRoyaltyStatement extends Model
{
    use HasUuids;

    protected $table = 'med_royalty_statements';

    protected $fillable = [
        'id',
        'statement_number',
        'license_id',
        'billing_period',
        'gross_revenue_minor',
        'total_distribution_fee_minor',
        'total_recouped_minor',
        'total_royalty_distributed_minor',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'gross_revenue_minor' => 'integer',
        'total_distribution_fee_minor' => 'integer',
        'total_recouped_minor' => 'integer',
        'total_royalty_distributed_minor' => 'integer',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(MedIpLicense::class, 'license_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MedRoyaltyStatementLine::class, 'statement_id');
    }
}
