<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedIpAsset extends Model
{
    protected $table = 'med_ip_assets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
