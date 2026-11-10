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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function getThemeForUser(int $userId): string
    {
        $pref = static::where('user_id', $userId)->first();

        return $pref?->theme ?? 'light';
    }

    public static function setThemeForUser(int $userId, string $theme): static
    {
        return static::updateOrCreate(
            ['user_id' => $userId],
            ['theme' => in_array($theme, ['light', 'dark', 'hc'], true) ? $theme : 'light']
        );
    }
}
