<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetCustomerPrivacyProfile extends Model
{
    protected $table = 'ret_customer_privacy_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'marketing_analytics_opt_out' => 'boolean',
    ];
}
