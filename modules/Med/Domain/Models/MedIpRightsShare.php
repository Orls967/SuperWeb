<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class MedIpRightsShare extends Model
{
    use HasUuids;

    protected $table = 'med_ip_rights_shares';

    protected $fillable = [
        'id',
        'ip_id',
        'rights_type',
        'rightsholder_party_id',
        'share_bps',
        'effective_date',
    ];

    protected $casts = [
        'share_bps' => 'integer',
        'effective_date' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException('MedIpRightsShare records are strictly immutable and append-only.');
        });

        static::deleting(function () {
            throw new RuntimeException('MedIpRightsShare records cannot be deleted.');
        });
    }

    public function ipAsset(): BelongsTo
    {
        return $this->belongsTo(MedIpAsset::class, 'ip_id');
    }
}
