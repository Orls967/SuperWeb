<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningSite extends Model
{
    protected $table = 'min_sites';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_code',
        'name',
        'commodity',
        'location',
    ];
}
