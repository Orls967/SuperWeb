<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Irisan Citra Tunggal DICOM SOP Instance Part 10 (Fase 105).
 *
 * @property int $id
 * @property int $series_id
 * @property string $sop_instance_uid
 * @property int $instance_number
 * @property string $sop_class_uid
 * @property int $rows
 * @property int $columns
 * @property int $bits_allocated
 * @property float $window_center
 * @property float $window_width
 * @property string $pixel_spacing
 * @property string $storage_path
 * @property string $checksum_sha256
 */
class DicomInstance extends Model
{
    use HasFactory;

    protected $table = 'hsp_dicom_instances';

    protected $fillable = [
        'series_id',
        'sop_instance_uid',
        'instance_number',
        'sop_class_uid',
        'rows',
        'columns',
        'bits_allocated',
        'window_center',
        'window_width',
        'pixel_spacing',
        'storage_path',
        'checksum_sha256',
    ];

    protected $casts = [
        'instance_number' => 'integer',
        'rows' => 'integer',
        'columns' => 'integer',
        'bits_allocated' => 'integer',
        'window_center' => 'float',
        'window_width' => 'float',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(ImagingSeries::class, 'series_id');
    }
}
