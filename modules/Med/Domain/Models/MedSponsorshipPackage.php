<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedSponsorshipPackage extends Model
{
    protected $table = 'med_sponsorship_packages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'total_sponsorship_minor' => 'integer',
        'bundled_channels' => 'array',
    ];
}
