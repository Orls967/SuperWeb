<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class ReinsuranceTreatyHistory extends Model
{
    use AppendOnly;

    protected $table = 'ins_rein_treaty_history';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'treaty_id',
        'from_status',
        'to_status',
        'reason',
        'actor_user_id',
        'approval_ref',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
