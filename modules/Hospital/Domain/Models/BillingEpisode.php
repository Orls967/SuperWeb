<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingEpisode extends Model
{
    protected $table = 'hsp_billing_episodes';

    protected $fillable = [
        'episode_code',
        'encounter_id',
        'escrow_deposit_idr',
        'total_charges_idr',
        'bpjs_coverage_idr',
        'insurance_copay_idr',
        'patient_share_idr',
        'status',
    ];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class, 'billing_episode_id');
    }
}
