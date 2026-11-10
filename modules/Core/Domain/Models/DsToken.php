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
        'token_value',
        'version',
        'label',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    /**
     * Scope token berdasarkan tema.
     */
    public function scopeForTheme(Builder $query, string $theme): Builder
    {
        return $query->where('theme', $theme);
    }

    /**
     * Scope token versi aktif/terbaru.
     */
    public function scopeLatestVersion(Builder $query): Builder
    {
        return $query->orderBy('version', 'desc');
    }

    /**
     * Cek apakah nilai token merupakan alias sintaks {token_name}.
     */
    public function isAlias(): bool
    {
        return str_starts_with(trim($this->token_value), '{') && str_ends_with(trim($this->token_value), '}');
    }

    /**
     * Ambil nama target alias bila token ini merupakan alias.
     */
    public function getAliasTarget(): ?string
    {
        if ($this->isAlias()) {
            return trim($this->token_value, '{} ');
        }

        return null;
    }
}
