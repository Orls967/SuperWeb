<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jadwal induk produksi (MPS) dengan time fence pembekuan. */
class MpsHeader extends Model
{
    use HasUuids;

    protected $table = 'mfg_mps_headers';

    protected $fillable = ['name', 'version', 'status', 'freeze_days', 'horizon_end', 'notes', 'created_by_user_id'];

    protected $casts = [
        'version' => 'integer', 'freeze_days' => 'integer',
        'horizon_end' => 'date', 'created_by_user_id' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(MpsLine::class, 'header_id');
    }
}
