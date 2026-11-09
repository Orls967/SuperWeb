<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformDx;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\StateMachines\SandboxEnvironmentStateMachine;
use Tests\TestCase;

class SandboxLifecycleStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_follows_strict_7_status_lifecycle(): void
    {
        $user = User::factory()->create();

        $sandbox = SandboxEnvironment::create([
            'name' => 'Feature Branch W8 Test',
            'slug' => 'feat-w8-test',
            'tier' => 'standard',
            'status' => SandboxStatus::REQUESTED->value,
            'owner_id' => $user->id,
            'idempotency_key' => 'idem-sbx-001',
            'expires_at' => now()->addHours(24),
            'metadata' => ['branch' => 'feature/w8-p1-kualitas-dx'],
        ]);

        $sm = new SandboxEnvironmentStateMachine($sandbox);
        expect($sm->getCurrentStatus())->toBe(SandboxStatus::REQUESTED);

        // requested -> provisioning
        $sm->transitionTo(SandboxStatus::PROVISIONING, 'Resource allocation initiated');
        expect($sandbox->fresh()->status)->toBe(SandboxStatus::PROVISIONING->value);

        // provisioning -> seeded
        $sm->transitionTo(SandboxStatus::SEEDED, 'Synthetic data loaded');
        expect($sandbox->fresh()->status)->toBe(SandboxStatus::SEEDED->value);

        // seeded -> ready
        $sm->transitionTo(SandboxStatus::READY, 'Environment healthy and reachable');
        expect($sandbox->fresh()->status)->toBe(SandboxStatus::READY->value);

        // ready -> expiring
        $sm->transitionTo(SandboxStatus::EXPIRING, 'Warning: TTL expires in 1 hour');
        expect($sandbox->fresh()->status)->toBe(SandboxStatus::EXPIRING->value);

        // expiring -> expired
        $sm->transitionTo(SandboxStatus::EXPIRED, 'TTL reached; environment locked');
        expect($sandbox->fresh()->status)->toBe(SandboxStatus::EXPIRED->value);

        // expired -> destroyed
        $sm->transitionTo(SandboxStatus::DESTROYED, 'Resources decommissioned and cleaned up');
        expect($sandbox->fresh()->status)->toBe(SandboxStatus::DESTROYED->value);

        // Record transisi append-only tersimpan 6 kali
        expect($sandbox->transitions()->count())->toBe(6);
    }

    public function test_invalid_transition_throws_domain_exception(): void
    {
        $user = User::factory()->create();

        $sandbox = SandboxEnvironment::create([
            'name' => 'Invalid Transition Sandbox',
            'slug' => 'inv-trans-sbx',
            'tier' => 'ephemeral',
            'status' => SandboxStatus::REQUESTED->value,
            'owner_id' => $user->id,
            'idempotency_key' => 'idem-sbx-002',
            'expires_at' => now()->addHours(2),
        ]);

        $sm = new SandboxEnvironmentStateMachine($sandbox);

        // Tidak boleh langsung loncat dari requested ke ready
        $this->expectException(\DomainException::class);
        $sm->transitionTo(SandboxStatus::READY, 'Direct jump not permitted');
    }

    public function test_sandbox_auto_expire_command_flags_overdue_environments(): void
    {
        $user = User::factory()->create();

        $activeSandbox = SandboxEnvironment::create([
            'name' => 'Still Active',
            'slug' => 'still-active',
            'tier' => 'standard',
            'status' => SandboxStatus::READY->value,
            'owner_id' => $user->id,
            'idempotency_key' => 'idem-active',
            'expires_at' => now()->addHours(5),
        ]);

        $expiredSandbox = SandboxEnvironment::create([
            'name' => 'Expired Candidate',
            'slug' => 'expired-cand',
            'tier' => 'standard',
            'status' => SandboxStatus::READY->value,
            'owner_id' => $user->id,
            'idempotency_key' => 'idem-expired',
            'expires_at' => now()->subMinute(),
        ]);

        $exitCode = Artisan::call('platform:sandbox-expire');
        expect($exitCode)->toBe(0);

        expect($activeSandbox->fresh()->status)->toBe(SandboxStatus::READY->value);
        expect($expiredSandbox->fresh()->status)->toBe(SandboxStatus::EXPIRED->value);
    }
}
