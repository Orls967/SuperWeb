<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Access recertification campaign entity (W8-D59).
 */
class CoreAccessRecertificationCampaign extends Model
{
    protected $table = 'core_access_recertification_campaigns';

    protected $fillable = [
        'campaign_code',
        'title',
        'scope',
        'deadline',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CoreAccessRecertificationItem::class, 'campaign_id');
    }
}
