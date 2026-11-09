<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Master Parameter Laboratorium LOINC 10.000 (Fase 105).
 * Standar Internasional Interoperabilitas Diagnostik LOINC.
 *
 * @property int $id
 * @property string $loinc_num
 * @property string $component
 * @property string $property
 * @property string $time_aspect
 * @property string $system_specimen
 * @property string $scale_type
 * @property string|null $method_type
 * @property string $class_name
 * @property float|null $reference_min
 * @property float|null $reference_max
 * @property string|null $unit
 * @property float|null $panic_low
 * @property float|null $panic_high
 * @property int $price_idr
 * @property bool $is_orderable
 * @property bool $is_active
 */
class LaboratoryLoincCatalog extends Model
{
    use HasFactory;

    protected $table = 'hsp_lab_catalogs';

    protected $fillable = [
        'loinc_num',
        'component',
        'property',
        'time_aspect',
        'system_specimen',
        'scale_type',
        'method_type',
        'class_name',
        'reference_min',
        'reference_max',
        'unit',
        'panic_low',
        'panic_high',
        'price_idr',
        'is_orderable',
        'is_active',
    ];

    protected $casts = [
        'reference_min' => 'float',
        'reference_max' => 'float',
        'panic_low' => 'float',
        'panic_high' => 'float',
        'price_idr' => 'integer',
        'is_orderable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke hasil pengujian lab.
     */
    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'test_code', 'loinc_num');
    }

    /**
     * Menilai apakah nilai uji berada dalam batas kritis (panic range).
     */
    public function isValuePanic(float $value): bool
    {
        if ($this->panic_low !== null && $value < $this->panic_low) {
            return true;
        }

        if ($this->panic_high !== null && $value > $this->panic_high) {
            return true;
        }

        return false;
    }

    /**
     * Menilai apakah nilai uji berada dalam batas rujukan fisiologis normal.
     */
    public function isValueNormal(float $value): bool
    {
        if ($this->reference_min !== null && $value < $this->reference_min) {
            return false;
        }

        if ($this->reference_max !== null && $value > $this->reference_max) {
            return false;
        }

        return true;
    }
}
