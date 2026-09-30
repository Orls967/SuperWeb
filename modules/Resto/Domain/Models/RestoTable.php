<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Resto\Domain\Enums\TableStatus;

class RestoTable extends Model
{
    protected $table = 'resto_tables';

    protected $fillable = [
        'outlet_id',
        'code',
        'seats',
        'zone',
        'status',
    ];

    protected $casts = [
        'status' => TableStatus::class,
        'seats' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'table_id');
    }

    public function activeSession()
    {
        return $this->hasOne(TableSession::class, 'table_id')->where('status', 'open')->latestOfMany();
    }
}
