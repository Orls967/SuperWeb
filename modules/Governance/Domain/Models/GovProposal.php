<?php

declare(strict_types=1);

namespace Modules\Governance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovProposal extends Model
{
    protected $table = 'gov_proposals';

    protected $fillable = [
        'proposal_code',
        'title',
        'proposal_type',
        'description',
        'action_payload',
        'quorum_weight_required',
        'merkle_root',
        'voting_starts_at',
        'voting_ends_at',
        'total_yes_weight',
        'total_no_weight',
        'total_abstain_weight',
        'status',
        'execution_reference_code',
        'epc_project_id',
    ];

    protected $casts = [
        'action_payload' => 'array',
        'voting_starts_at' => 'datetime',
        'voting_ends_at' => 'datetime',
        'quorum_weight_required' => 'integer',
        'total_yes_weight' => 'integer',
        'total_no_weight' => 'integer',
        'total_abstain_weight' => 'integer',
        'epc_project_id' => 'string',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(GovVote::class, 'proposal_id');
    }
}
