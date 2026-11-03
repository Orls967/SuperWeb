<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Polyclinic extends Model
{
    protected $table = 'hsp_polyclinics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class, 'polyclinic_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(QueueTicket::class, 'polyclinic_id');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(OutpatientEncounter::class, 'polyclinic_id');
    }
}
