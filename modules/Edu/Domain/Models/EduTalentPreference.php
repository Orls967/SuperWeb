<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduTalentPreference extends Model
{
    protected $table = 'edu_talent_preferences';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'student_user_id',
        'opening_id',
        'rank_order',
    ];

    protected $casts = [
        'student_user_id' => 'integer',
        'rank_order' => 'integer',
    ];

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id', 'id');
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(EduJobOpening::class, 'opening_id', 'id');
    }
}
