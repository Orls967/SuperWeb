<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Laporan sell-out distributor (43.3) + deteksi anomali. */
class SelloutReport extends Model
{
    use HasUuids;

    protected $table = 'dist_sellout_reports';

    protected $fillable = [
        'distributor_id', 'outlet_id', 'period_date', 'status', 'total_qty',
        'total_value_idr', 'anomalies', 'notes', 'reported_by_user_id',
    ];

    protected $casts = [
        'period_date' => 'date', 'total_qty' => 'decimal:6', 'total_value_idr' => 'integer',
        'anomalies' => 'array', 'reported_by_user_id' => 'integer',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(DistOutlet::class, 'outlet_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SelloutLine::class, 'report_id');
    }
}
