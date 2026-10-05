<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasUuids;

    protected $table = 'ptn_partners';

    protected $fillable = [
        'code', 'name', 'kind', 'party_id', 'owner_user_id', 'status',
        'contract_ref', 'risk_score', 'notes',
    ];

    protected $casts = [
        'owner_user_id' => 'integer',
        'risk_score' => 'integer',
    ];

    public const KINDS = ['strategic', 'tech', 'channel', 'franchise', 'jv', 'research', 'csr'];

    public const STATUSES = ['prospect', 'due_diligence', 'negotiation', 'active', 'review', 'exit'];

    public function dueDiligences(): HasMany
    {
        return $this->hasMany(DueDiligence::class, 'partner_id');
    }

    public function jointPlans(): HasMany
    {
        return $this->hasMany(JointPlan::class, 'partner_id');
    }

    public function revenueShares(): HasMany
    {
        return $this->hasMany(RevenueShare::class, 'partner_id');
    }

    public function cosellListings(): HasMany
    {
        return $this->hasMany(CosellListing::class, 'partner_id');
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(PartnerScorecard::class, 'partner_id');
    }

    public function intellectualProperties(): HasMany
    {
        return $this->hasMany(IntellectualProperty::class, 'partner_id');
    }

    public function exitTransitions(): HasMany
    {
        return $this->hasMany(ExitTransition::class, 'partner_id');
    }
}
