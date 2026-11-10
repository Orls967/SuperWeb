<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DsToken extends Model
{
    protected $table = 'core_ds_tokens';

    protected $fillable = [
        'theme',
        'token_name',
        'token_key',
        'token_type',
        'token_value',
        'raw_value',
        'resolved_value',
        'is_semantic',
        'description',
        'version',
        'label',
    ];

    protected $casts = [
        'is_semantic' => 'boolean',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (DsToken $t) {
            if (empty($t->token_key) && ! empty($t->token_name)) {
                $t->token_key = $t->token_name;
            }
            if (empty($t->token_name) && ! empty($t->token_key)) {
                $t->token_name = $t->token_key;
            }
            if (empty($t->raw_value) && ! empty($t->token_value)) {
                $t->raw_value = $t->token_value;
            }
            if (empty($t->token_value) && ! empty($t->raw_value)) {
                $t->token_value = $t->raw_value;
            }
            if (empty($t->resolved_value)) {
                $t->resolved_value = $t->raw_value;
            }
        });
    }

    public function scopeTheme(Builder $query, string $theme): Builder
    {
        return $query->where('theme', $theme);
    }

    public function scopeLatestVersion(Builder $query): Builder
    {
        return $query->orderBy('version', 'desc');
    }
}
