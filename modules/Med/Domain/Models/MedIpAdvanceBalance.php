<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedIpAdvanceBalance extends Model
{
    use HasUuids;

    protected $table = 'med_ip_advance_balances';

    protected $fillable = [
        'id',
        'license_id',
        'rightsholder_party_id',
        'initial_advance_minor',
        'recouped_amount_minor',
        'remaining_balance_minor',
        'idempotency_key',
    ];

    protected $casts = [
        'initial_advance_minor' => 'integer',
        'recouped_amount_minor' => 'integer',
        'remaining_balance_minor' => 'integer',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(MedIpLicense::class, 'license_id');
    }
}
