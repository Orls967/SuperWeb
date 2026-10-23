<?php

declare(strict_types=1);

namespace Modules\Agri\Domain\Models\Ndvi;

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
        'total_yes_weight',
        'total_no_weight',
        'total_abstain_weight',
        'status',
        'execution_reference_code',
    ];

    protected $casts = [
        'action_payload' => 'array',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(GovVote::class, 'proposal_id');
    }
}
