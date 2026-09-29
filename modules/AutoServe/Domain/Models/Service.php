<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class Service extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'serve_services';

    protected $fillable = ['name', 'description', 'price', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'service_id');
    }

    /** Scope: hanya service yang aktif */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
