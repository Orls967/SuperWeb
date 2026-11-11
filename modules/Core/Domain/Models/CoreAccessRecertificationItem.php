<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Access recertification audit item (W8-D59).
 */
class CoreAccessRecertificationItem extends Model
{
    protected $table = 'core_access_recertification_items';

    protected $fillable = [
        'campaign_id',
        'grant_id',
        'response',
        'responded_at',
        'reviewer_id',
    ];

    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'grant_id' => 'integer',
            'reviewer_id' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CoreAccessRecertificationCampaign::class, 'campaign_id');
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(CoreAccessGrant::class, 'grant_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
