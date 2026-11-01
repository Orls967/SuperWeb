<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedIpLicense extends Model
{
    protected $table = 'med_ip_licenses';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'license_code',
        'contract_number',
        'ip_id',
        'licensee_entity_id',
        'rights_type',
        'channel',
        'territory',
        'start_date',
        'end_date',
        'is_exclusive',
        'royalty_rate_pct',
        'minimum_guarantee_minor',
        'advance_payment_minor',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_exclusive' => 'boolean',
        'royalty_rate_pct' => 'float',
        'minimum_guarantee_minor' => 'integer',
        'advance_payment_minor' => 'integer',
    ];

    public function ipAsset(): BelongsTo
    {
        return $this->belongsTo(MedIpAsset::class, 'ip_id');
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(MedLicenseRevenue::class, 'license_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(MedRoyaltyStatement::class, 'license_id');
    }
}
