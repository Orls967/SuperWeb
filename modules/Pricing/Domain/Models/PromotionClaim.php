<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Klaim promo distributor dengan bukti + approval (44.3). */
class PromotionClaim extends Model
{
    use HasUuids;

    protected $table = 'pric_promotion_claims';

    protected $fillable = [
        'promotion_id', 'distributor_id', 'source_ref', 'amount_idr', 'status',
        'evidence_note', 'evidence_document', 'approval_id', 'submitted_by_user_id',
    ];

    protected $casts = [
        'distributor_id' => 'string', 'amount_idr' => 'integer', 'approval_id' => 'integer',
        'submitted_by_user_id' => 'integer',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }
}
