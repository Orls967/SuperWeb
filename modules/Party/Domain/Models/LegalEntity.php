<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use App\Security\BlindIndex;
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
        'npwp', 'npwp_bidx', 'nib', 'functional_currency', 'fiscal_year_start',
        'ledger_prefix', 'account_map', 'is_active',
    ];

    protected $casts = [
        'npwp' => 'encrypted',
        'account_map' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $entity) {
            if ($entity->isDirty('npwp')) {
                $entity->npwp_bidx = BlindIndex::forNpwp($entity->npwp);
            }
        });
    }

    /**
     * Find legal entity by NPWP via deterministic blind index.
     */
    public static function findByNpwp(string $npwp): ?self
    {
        $bidx = BlindIndex::forNpwp($npwp);

        return self::where('npwp_bidx', $bidx)->first();
    }

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
