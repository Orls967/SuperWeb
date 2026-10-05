<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Versi biaya standar (draft → pending_approval → approved). */
class CostVersion extends Model
{
    use HasUuids;

    protected $table = 'mfg_cost_versions';

    protected $fillable = ['name', 'version', 'status', 'approval_id', 'notes', 'created_by_user_id', 'approved_at'];

    protected $casts = [
        'version' => 'integer', 'approval_id' => 'integer', 'created_by_user_id' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function standardCosts(): HasMany
    {
        return $this->hasMany(StandardCost::class, 'version_id');
    }

    /** Versi biaya yang disetujui & berlaku. */
    public static function approved(): ?self
    {
        return static::where('status', 'approved')->orderByDesc('version')->first();
    }
}
