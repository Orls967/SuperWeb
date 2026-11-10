<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DsUserPref extends Model
{
    protected $table = 'core_ds_user_prefs';

    protected $fillable = [
        'user_id',
        'theme',
        'density',
        'font_scale',
        'reduced_motion',
        'high_contrast',
    ];

    protected $casts = [
        'font_scale' => 'integer',
        'reduced_motion' => 'boolean',
        'high_contrast' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function getThemeForUser(?int $userId): string
    {
        if (! $userId) {
            return (string) session('ds_theme', 'light');
        }

        return (string) (self::where('user_id', $userId)->value('theme') ?: session('ds_theme', 'light'));
    }
}
