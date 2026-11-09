<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Illuminate\Support\Str;
use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Enums\SandboxTier;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\StateMachines\SandboxEnvironmentStateMachine;

class CreateSandboxAction
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function execute(
        string $name,
        int $ownerId,
        SandboxTier $tier = SandboxTier::STANDARD,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): SandboxEnvironment {
        $slug = Str::slug($name);
        $idemKey = $idempotencyKey ?? sprintf('plt:create_sandbox:%s:%d', $slug, $ownerId);

        $existing = SandboxEnvironment::where('idempotency_key', $idemKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $ttlHours = $tier->defaultTtlHours();
        $sandboxSlug = $slug . '-' . substr(hash('sha256', $idemKey), 0, 6);

        $sandbox = SandboxEnvironment::create([
            'name' => $name,
            'slug' => $sandboxSlug,
            'tier' => $tier->value,
            'status' => SandboxStatus::REQUESTED->value,
            'owner_id' => $ownerId,
            'idempotency_key' => $idemKey,
            'expires_at' => now()->addHours($ttlHours),
            'metadata' => $metadata,
        ]);

        return $sandbox;
    }
}
