<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformDx;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Domain\Actions\AnonymizeDatasetAction;
use Modules\Platform\Domain\Actions\MaskPiiAction;
use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Tests\TestCase;

class PlatformDataPrivacyAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_pii_masking_action_anonymizes_sensitive_fields_deterministically(): void
    {
        $action = new MaskPiiAction();

        $originalEmail = 'developer.private@typeshit.com';
        $originalPhone = '+6281234567890';
        $originalNik = '3201234567890001';

        $maskedEmail = $action->maskEmail($originalEmail);
        $maskedPhone = $action->maskPhone($originalPhone);
        $maskedNik = $action->maskNationalId($originalNik);

        expect($maskedEmail)->not->toBe($originalEmail);
        expect($maskedEmail)->toMatch('/^d\*\*\*e@typeshit\.com$/');
        expect($maskedPhone)->not->toBe($originalPhone);
        expect($maskedPhone)->toMatch('/^\+6281\*\*\*\*\*7890$/');
        expect($maskedNik)->not->toBe($originalNik);
        expect($maskedNik)->toMatch('/^3201\*\*\*\*\*\*\*\*0001$/');

        // Deterministik: input yang sama menghasilkan output yang konsisten
        expect($action->maskEmail($originalEmail))->toBe($maskedEmail);
    }

    public function test_anonymize_dataset_action_cleanses_database_payloads(): void
    {
        $anonymizer = new AnonymizeDatasetAction(new MaskPiiAction());

        $payload = [
            'id' => 101,
            'name' => 'John Doe',
            'email' => 'john.doe@enterprise.com',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman Kav 20, Jakarta Pusat',
            'account_number' => '123456789012',
        ];

        $cleansed = $anonymizer->anonymizeRecord('users', $payload);

        expect($cleansed['email'])->not->toBe($payload['email']);
        expect($cleansed['phone'])->not->toBe($payload['phone']);
        expect($cleansed['address'])->toContain('Jl. [MASKED]');
    }

    public function test_dev_portal_enforces_anti_idor_policy_on_sandboxes(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $sandbox = SandboxEnvironment::create([
            'name' => 'Confidential QA Sandbox',
            'slug' => 'confidential-qa',
            'tier' => 'isolated',
            'status' => SandboxStatus::READY->value,
            'owner_id' => $owner->id,
            'idempotency_key' => 'idem-conf-01',
            'expires_at' => now()->addHours(12),
        ]);

        // Owner dapat mengakses detail
        $ownerResponse = $this->actingAs($owner)->get("/platform/dev/sandboxes/{$sandbox->id}");
        $ownerResponse->assertStatus(200);

        // Stranger ditolak dengan 403 Forbidden (Anti-IDOR)
        $strangerResponse = $this->actingAs($stranger)->get("/platform/dev/sandboxes/{$sandbox->id}");
        $strangerResponse->assertStatus(403);
    }

    public function test_correlation_id_is_propagated_via_middleware_and_trace_service(): void
    {
        $user = User::factory()->create();
        $correlationId = 'req-trace-test-uuid-777';

        $response = $this->actingAs($user)->withHeaders([
            'X-Correlation-Id' => $correlationId,
        ])->get('/platform/dev');

        $response->assertStatus(200);
        $response->assertHeader('X-Correlation-Id', $correlationId);
    }

    public function test_platform_audit_command_passes_on_clean_and_fails_on_corruption(): void
    {
        // 1. Audit pada state bersih
        $cleanExit = Artisan::call('platform:audit');
        expect($cleanExit)->toBe(0);

        // 2. Audit dengan fixture manipulasi / korupsi terdeteksi
        $corruptExit = Artisan::call('platform:audit', ['--corrupt-test' => true]);
        expect($corruptExit)->not->toBe(0);
    }
}

