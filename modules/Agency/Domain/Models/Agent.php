<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Agen penjualan/broker/reseller/afiliasi + hirarki upline (45.1). */
class Agent extends Model
{
    use HasUuids;

    protected $table = 'agy_agents';

    protected $fillable = [
        'code', 'name', 'kind', 'parent_id', 'party_id', 'owner_user_id',
        'region_code', 'status', 'max_downline_levels', 'notes',
    ];

    protected $casts = [
        'owner_user_id' => 'integer', 'max_downline_levels' => 'integer',
    ];

    public const KINDS = ['sales_agent', 'broker', 'reseller', 'affiliate', 'sole_agent'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(AgentContract::class, 'agent_id');
    }

    public function schemes(): HasMany
    {
        return $this->hasMany(CommissionScheme::class, 'agent_id');
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class, 'agent_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'agent_id');
    }

    public function canEarn(): bool
    {
        return $this->status === 'active';
    }

    /** Deret upline dari diri sendiri ke root (maks max_downline_levels level override). */
    public function uplines(int $maxDepth): array
    {
        $chain = [];
        $current = $this->parent;
        $depth = 0;
        while ($current !== null && $depth < $maxDepth) {
            $chain[] = $current;
            $current = $current->parent;
            $depth++;
        }

        return $chain;
    }
}
