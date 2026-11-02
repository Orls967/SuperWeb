<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EduPlacement extends Model
{
    protected $table = 'edu_placements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'placement_code',
        'student_user_id',
        'opening_id',
        'placed_at',
        'guarantee_days',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'student_user_id' => 'integer',
        'guarantee_days' => 'integer',
        'placed_at' => 'date',
    ];

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id', 'id');
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(EduJobOpening::class, 'opening_id', 'id');
    }

    public function headhunterFee(): HasOne
    {
        return $this->hasOne(EduHeadhunterFee::class, 'placement_id', 'id');
    }
}
