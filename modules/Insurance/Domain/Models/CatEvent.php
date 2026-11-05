<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class CatEvent extends Model
{
    use AppendOnly;

    protected $table = 'ins_cat_events';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'run_id',
        'event_no',
        'peril',
        'region_code',
        'intensity_bps',
        'annual_rate_ppm',
    ];
}
