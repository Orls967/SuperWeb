<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedLicenseRevenue extends Model
{
    use HasUuids;

    protected $table = 'med_license_revenues';

    protected $fillable = [
        'id',
        'license_id',
        'billing_period',
        'gross_revenue_minor',
        'distribution_fee_bps',
        'distribution_fee_minor',
        'recouped_advance_minor',
        'net_distributable_minor',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'gross_revenue_minor' => 'integer',
        'distribution_fee_bps' => 'integer',
        'distribution_fee_minor' => 'integer',
        'recouped_advance_minor' => 'integer',
        'net_distributable_minor' => 'integer',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(MedIpLicense::class, 'license_id');
    }
}
