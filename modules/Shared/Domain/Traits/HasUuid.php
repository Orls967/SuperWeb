<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            $uuidColumn = $model->getUuidColumnName();
            if (empty($model->{$uuidColumn})) {
                $model->{$uuidColumn} = (string) Str::uuid();
            }
        });
    }

    public function getUuidColumnName(): string
    {
        return 'uuid';
    }

    public function scopeByUuid(Builder $query, string $uuid): Builder
    {
        return $query->where($this->getUuidColumnName(), $uuid);
    }

    public static function findByUuid(string $uuid): ?static
    {
        return static::where((new static)->getUuidColumnName(), $uuid)->first();
    }

    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where((new static)->getUuidColumnName(), $uuid)->firstOrFail();
    }
}
