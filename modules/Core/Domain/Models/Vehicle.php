<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Shared\Domain\Traits\HasUuid;

class Vehicle extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'core_vehicles';

    protected $fillable = [
        'uuid',
        'user_id',
        'car_id',
        'plate_number',
        'vin',
        'color',
        'odometer_km',
        'acquired_at',
        'acquired_via_type',
        'acquired_via_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'odometer_km' => 'integer',
            'acquired_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }

    public function acquiredVia(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function events(): HasMany
    {
        return $this->hasMany(VehicleEvent::class, 'vehicle_id')->orderBy('sequence');
    }

    public function getPassportUrl(): string
    {
        return URL::signedRoute('passport.show', ['uuid' => $this->uuid]);
    }
}
