<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\C2m;

use Illuminate\Database\Eloquent\Model;

class VmiReplenishment extends Model
{
    protected $table = 'mfg_vmi_replenishments';

    protected $guarded = [];
}
