<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    use HasUuids;

    protected $table = 'pty_legal_entities';

    protected $fillable = [
        'name', 'short_name', 'entity_type', 'parent_id',
        'npwp', 'nib', 'functional_currency', 'fiscal_year_start',
        'ledger_prefix', 'account_map', 'is_active',
    ];

    protected $casts = [
        'account_map' => 'array',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class, 'legal_entity_id');
    }

    /** Return all subsidiaries recursively. */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }
}
