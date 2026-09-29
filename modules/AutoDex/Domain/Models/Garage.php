<?php

declare(strict_types=1);

namespace Modules\AutoDex\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Garage extends Model
{
    protected $table = 'dex_garages';

    protected $fillable = [
        'user_id', 'car_id', 'plate_number', 'color', 'year_bought', 'nickname', 'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
