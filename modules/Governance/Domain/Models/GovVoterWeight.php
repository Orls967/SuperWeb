<?php

declare(strict_types=1);

namespace Modules\Governance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GovVoterWeight extends Model
{
    protected $table = 'gov_voter_weights';

    protected $fillable = [
        'voter_user_id',
        'voter_role',
        'voting_weight',
        'passport_hash',
    ];

    protected $casts = [
        'voting_weight' => 'integer',
        'voter_user_id' => 'integer',
    ];
}
