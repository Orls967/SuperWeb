<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class CatExposure extends Model
{
    use AppendOnly;

    protected $table = 'ins_cat_exposures';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'run_id',
        'region_code',
        'peril',
        'exposure_idr',
        'policy_count',
    ];
}
