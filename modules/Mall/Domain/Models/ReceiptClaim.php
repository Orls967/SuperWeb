<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Mall\Domain\Enums\ReceiptClaimStatus;
use Modules\Shared\Domain\Traits\HasUuid;

class ReceiptClaim extends Model
{
    use HasUuid;

    protected $table = 'mall_receipt_claims';

    protected $fillable = [
        'uuid',
        'user_id',
        'tenant_id',
        'receipt_number',
        'receipt_date',
        'receipt_amount',
        'points_earned',
        'tier_at_claim',
        'status',
        'rejection_reason',
        'processed_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'receipt_amount' => 'integer',
        'points_earned' => 'integer',
        'tier_at_claim' => LoyaltyTier::class,
        'status' => ReceiptClaimStatus::class,
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
