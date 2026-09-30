<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class VoucherTemplate extends Model
{
    use HasUuid;

    protected $table = 'mall_voucher_templates';

    protected $fillable = [
        'uuid',
        'code',
        'title',
        'description',
        'points_required',
        'nominal_value',
        'min_spend',
        'validity_days',
        'is_active',
    ];

    protected $casts = [
        'points_required' => 'integer',
        'nominal_value' => 'integer',
        'min_spend' => 'integer',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'template_id');
    }
}
