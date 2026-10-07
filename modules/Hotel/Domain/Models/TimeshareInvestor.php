<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TimeshareInvestor extends Model
{
    protected $table = 'htl_timeshare_investors';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'timeshare_unit_id',
        'investor_user_id',
        'token_shares',
    ];

    protected $casts = [
        'token_shares' => 'integer',
    ];
}
