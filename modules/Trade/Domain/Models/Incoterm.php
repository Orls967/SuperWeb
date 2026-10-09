<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Incoterm extends Model
{
    protected $table = 'trd_incoterms';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'risk_transfer_point',
        'cost_responsibility',
    ];
}
