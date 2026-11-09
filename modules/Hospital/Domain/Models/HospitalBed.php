<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HospitalBed extends Model
{
    protected $table = 'hsp_beds';

    protected $fillable = [
        'bed_code',
        'ward_name',
        'room_number',
        'bed_class',
        'rate_per_day_idr',
        'status',
        'current_encounter_id',
    ];

    public function getClassAttribute(): ?string
    {
        return $this->attributes['bed_class'] ?? null;
    }

    public function setClassAttribute(?string $value): void
    {
        $this->attributes['bed_class'] = $value;
    }

    public function getDepartmentAttribute(): ?string
    {
        return $this->attributes['ward_name'] ?? null;
    }

    public function setDepartmentAttribute(?string $value): void
    {
        $this->attributes['ward_name'] = $value;
    }
}
