<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\VehicleEvent;
use Modules\Shared\Domain\Traits\HasUuid;

/**
 * Cross-module contract base model for Vehicle (DR4-4, W8-D44).
 */
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
        return $this->belongsTo('Modules\AutoDex\Domain\Models\Car', 'car_id');
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
        if (empty($this->uuid)) {
            $this->forceFill(['uuid' => (string) Str::uuid()])->save();
        }

        return URL::signedRoute('passport.show', ['uuid' => $this->uuid]);
    }
}
