<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferPricingDocument extends Model
{
    protected $table = 'ic_tp_documents';

    protected $fillable = [
        'document_number',
        'entity_code',
        'entity_id',
        'fiscal_year',
        'document_type',
        'document_hash',
        'status',
        'generated_at',
        'created_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'entity_id');
    }
}
