<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybTradeCustodyInspection extends Model
{
    protected $table = 'syb_trade_custody_inspections';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'custody_document_id',
        'inspector_party_ref',
        'physical_location',
        'seal_number',
        'condition_grade',
        'inspection_notes',
        'inspected_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'inspected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SybTradeCustodyDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SybTradeCustodyDocument::class, 'custody_document_id');
    }
}
