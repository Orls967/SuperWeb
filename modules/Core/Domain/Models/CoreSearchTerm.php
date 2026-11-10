<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representasi term frekuensi dan posting list indeks terbalik.
 */
class CoreSearchTerm extends Model
{
    protected $table = 'core_search_terms';

    protected $fillable = [
        'search_index_id',
        'term',
        'term_hash',
        'frequency',
        'field_weight',
    ];

    protected $casts = [
        'frequency' => 'integer',
        'field_weight' => 'integer',
    ];

    public function searchIndex(): BelongsTo
    {
        return $this->belongsTo(CoreSearchIndex::class, 'search_index_id', 'id');
    }
}
