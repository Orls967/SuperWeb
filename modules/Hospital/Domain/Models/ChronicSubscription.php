<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChronicSubscription extends Model
{
    protected $table = 'hsp_chronic_subscriptions';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_quantity' => 'integer',
            'unit_price_idr' => 'integer',
            'next_refill_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranch::class, 'pharmacy_branch_id');
    }
}
