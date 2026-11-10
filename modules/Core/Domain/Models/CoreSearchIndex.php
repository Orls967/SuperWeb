<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model representasi dokumen terindeks pada sistem pencarian global.
 */
class CoreSearchIndex extends Model
{
    use HasUuids;

    protected $table = 'core_search_indices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'module',
        'entity_type',
        'entity_id',
        'title',
        'content',
        'metadata',
        'required_permission',
        'scope_type',
        'scope_id',
        'document_length',
        'indexed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'document_length' => 'integer',
        'indexed_at' => 'datetime',
    ];

    /**
     * Relasi ke posting list terms.
     */
    public function terms(): HasMany
    {
        return $this->hasMany(CoreSearchTerm::class, 'search_index_id', 'id');
    }
}
