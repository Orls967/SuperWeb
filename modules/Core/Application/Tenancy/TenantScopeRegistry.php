<?php

declare(strict_types=1);

namespace Modules\Core\Application\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Core\Domain\Models\CoreTenantScopeBypass;

class TenantScopeRegistry
{
    private static bool $bypassed = false;

    private static bool $strict = false;

    /** @var array<int, TenantScopedModelProvider> */
    private array $providers = [];

    /** @var array<string, array{module: string, class: class-string<Model>}> */
    private array $registeredModels = [];

    public function registerProvider(TenantScopedModelProvider $provider): void
    {
        $this->providers[] = $provider;
        foreach ($provider->scopedModels() as $descriptor) {
            $modelClass = is_array($descriptor) ? ($descriptor['model'] ?? null) : $descriptor;
            if ($modelClass !== null) {
                $this->registerModel($provider->moduleName(), $modelClass);
            }
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function registerModel(string $module, string $modelClass): void
    {
        if (isset($this->registeredModels[$modelClass])) {
            return;
        }

        $this->registeredModels[$modelClass] = [
            'module' => $module,
            'class' => $modelClass,
        ];

        if (is_subclass_of($modelClass, Model::class)) {
            $modelClass::addGlobalScope(new TenantIsolationScope);
        }
    }

    public static function isBypassed(): bool
    {
        return self::$bypassed;
    }

    public static function setStrict(bool $strict): void
    {
        self::$strict = $strict;
    }

    public static function isStrict(): bool
    {
        return self::$strict;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withStrictEnforcement(callable $callback): mixed
    {
        $prev = self::$strict;
        self::$strict = true;
        try {
            return $callback();
        } finally {
            self::$strict = $prev;
        }
    }

    /**
     * Run callback bypassing tenant scoping with audit trail in core_tenant_scope_bypasses.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutTenantScope(string $ability, string $reason, callable $callback): mixed
    {
        $previousBypassed = self::$bypassed;
        self::$bypassed = true;

        $tenantId = TenantContext::current()?->tenantId;
        $actorId = Auth::id();

        try {
            try {
                CoreTenantScopeBypass::create([
                    'tenant_id' => $tenantId,
                    'actor_user_id' => $actorId,
                    'ability' => $ability,
                    'model_class' => '*',
                    'reason' => $reason,
                ]);
            } catch (\Throwable) {
                // Ignore audit persistence failure in transient/unmigrated contexts
            }

            return $callback();
        } finally {
            self::$bypassed = $previousBypassed;
        }
    }

    /**
     * @return array<string, array{module: string, class: class-string<Model>}>
     */
    public function getRegisteredModels(): array
    {
        return $this->registeredModels;
    }

    /**
     * @return list<string>
     */
    public function registeredModels(): array
    {
        return array_keys($this->registeredModels);
    }

    /**
     * @return array<int, TenantScopedModelProvider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
