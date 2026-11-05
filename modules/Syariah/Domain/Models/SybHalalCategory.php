<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybHalalCategory extends Model
{
    protected $table = 'syb_halal_categories';

    protected $fillable = [
        'category_code',
        'name',
        'line_code',
        'is_halal_mandatory',
        'is_strictly_prohibited',
        'risk_level',
    ];

    protected function casts(): array
    {
        return [
            'is_halal_mandatory' => 'boolean',
            'is_strictly_prohibited' => 'boolean',
        ];
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(SybHalalMerchant::class, 'category_code', 'category_code');
    }
}
