<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AquacultureEscrowSettlement extends Model
{
    protected $table = 'mar_escrow_settlements';

    protected $fillable = [
        'settlement_code',
        'export_order_id',
        'farmer_id',
        'gross_harvest_value_minor_cents',
        'feed_deduction_minor_cents',
        'hatchery_seed_deduction_minor_cents',
        'quality_grade_premium_minor_cents',
        'net_farmer_payout_minor_cents',
        'escrow_hold_reference',
        'released_at',
        'status',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(AquacultureExportOrder::class, 'export_order_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }
}
