<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
        'label',
        'description',
    ];

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')
            ->withPivot(['entity_type', 'entity_id'])
            ->withTimestamps();
    }

    public function givePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::firstOrCreate(
                ['name' => $permission],
                ['label' => ucwords(str_replace(['.', '_'], ' ', $permission))]
            );
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }
}
