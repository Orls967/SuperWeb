<?php

declare(strict_types=1);

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;

class GovVoterWeight extends Model
{
    protected $table = 'agri_voter_weights';

    protected $fillable = [
        'voter_user_id',
        'voter_role',
        'voting_weight',
        'passport_hash',
    ];
}
