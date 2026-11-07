<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MiningSite extends Model
{
    use HasUuids;

    protected $table = 'min_sites';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_code',
        'name',
        'commodity',
        'timezone',
        'location',
        'status',
    ];
}
