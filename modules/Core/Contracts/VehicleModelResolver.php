<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dynamic resolver for Vehicle Eloquent model class and queries (DR4-4, W8-D44).
 * Decouples business modules from direct Modules\Core\Domain\Models\Vehicle import.
 */
final class VehicleModelResolver
{
    /** @var class-string<Model> */
    private static string $modelClass = Vehicle::class;

    /**
     * @param  class-string<Model>  $class
     */
    public static function bindModelClass(string $class): void
    {
        self::$modelClass = $class;
    }

    /**
     * @return class-string<Model>
     */
    public static function modelClass(): string
    {
        return self::$modelClass;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): Model
    {
        $class = self::modelClass();

        return $class::create($attributes);
    }

    public static function find(int $id): ?Model
    {
        $class = self::modelClass();

        return $class::find($id);
    }

    public static function findOrFail(int $id): Model
    {
        $class = self::modelClass();

        return $class::findOrFail($id);
    }

    public static function query(): Builder
    {
        $class = self::modelClass();

        return $class::query();
    }

    public static function withTrashed(): Builder
    {
        $class = self::modelClass();

        /** @var Builder $query */
        $query = method_exists($class, 'withTrashed')
            ? $class::withTrashed()
            : $class::query();

        return $query;
    }

    /**
     * @param  array<string, mixed>|string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     */
    public static function where($column, $operator = null, $value = null): Builder
    {
        $class = self::modelClass();

        return $class::where($column, $operator, $value);
    }
}
