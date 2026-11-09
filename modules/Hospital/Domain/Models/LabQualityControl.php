<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model Evaluasi Kendali Mutu Analitik Westgard Multirules (Fase 105).
 *
 * @property int $id
 * @property string $analyzer_code
 * @property string $lot_number
 * @property string $control_level
 * @property string $test_code
 * @property float $target_mean
 * @property float $target_sd
 * @property float $measured_value
 * @property float $z_score
 * @property string|null $westgard_violation
 * @property bool $is_rejected
 * @property bool $is_resolved
 * @property int|null $technologist_id
 * @property Carbon $evaluated_at
 */
class LabQualityControl extends Model
{
    use HasFactory;

    protected $table = 'hsp_lab_quality_controls';

    protected $fillable = [
        'analyzer_code',
        'lot_number',
        'control_level',
        'test_code',
        'target_mean',
        'target_sd',
        'measured_value',
        'z_score',
        'westgard_violation',
        'is_rejected',
        'is_resolved',
        'technologist_id',
        'evaluated_at',
    ];

    protected $casts = [
        'target_mean' => 'float',
        'target_sd' => 'float',
        'measured_value' => 'float',
        'z_score' => 'float',
        'is_rejected' => 'boolean',
        'is_resolved' => 'boolean',
        'evaluated_at' => 'datetime',
    ];

    public function technologist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technologist_id');
    }
}
