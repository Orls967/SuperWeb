<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplier\Domain\Models\Supplier;

class TenderBid extends Model
{
    protected $table = 'prc_tender_bids';

    protected $fillable = [
        'tender_id', 'supplier_id', 'seal_hash', 'sealed_at', 'offer', 'opened_at',
        'total_score', 'is_winner', 'notes', 'approval_id',
    ];

    protected $casts = [
        'sealed_at' => 'datetime', 'opened_at' => 'datetime', 'offer' => 'array',
        'total_score' => 'decimal:4', 'is_winner' => 'boolean', 'approval_id' => 'integer',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class, 'tender_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function isSealIntact(array $offer): bool
    {
        return hash_equals($this->seal_hash, hash('sha256', json_encode($offer, JSON_UNESCAPED_SLASHES)));
    }
}
