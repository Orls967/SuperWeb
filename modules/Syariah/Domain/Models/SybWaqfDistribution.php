<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybWaqfDistribution extends Model
{
    protected $table = 'syb_waqf_distributions';

    protected $fillable = [
        'waqf_principal_id',
        'recipient_party_name',
        'benefit_amount_idr',
        'distribution_date',
        'idempotency_key',
    ];

    protected $casts = [
        'waqf_principal_id' => 'integer',
        'benefit_amount_idr' => 'integer',
        'distribution_date' => 'date',
    ];

    public function principal(): BelongsTo
    {
        return $this->belongsTo(SybWaqfPrincipal::class, 'waqf_principal_id');
    }
}
