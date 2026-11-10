<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model penanda favorit atau pinned shortcuts pada antarmuka Command Palette.
 */
class CoreUserSearchPin extends Model
{
    protected $table = 'core_user_search_pins';

    protected $fillable = [
        'user_id',
        'title',
        'url',
        'category',
        'badge',
        'badge_color',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];
}
