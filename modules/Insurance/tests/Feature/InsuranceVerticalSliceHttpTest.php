<?php

declare(strict_types=1);

namespace Modules\Insurance\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\InsAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Insurance\database\seeders\InsuranceDemoSeeder;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Tests\TestCase;

class InsuranceVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new InsuranceDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_insurance_index_and_issue(): void
    {
        $customer = User::where('email', 'insured_user@example.com')->firstOrFail();
        $product = InsuranceProduct::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($customer)->get(route('insurance.index'));
        $response->assertOk();
        $response->assertSeeText('InsurTech & Claims Autopilot');
        $response->assertSee($product->name);

        // 2. Issue embedded policy
        $issueResponse = $this->actingAs($customer)->post(route('insurance.products.issue', $product));

        $issueResponse->assertRedirect(route('insurance.index'));
        $issueResponse->assertSessionHas('success');

        $this->assertDatabaseHas('ins_policies', [
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'status' => 'active',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        // All web roles except unauthorized like 'supplier'
        $unauthorized = User::factory()->create(['role' => 'supplier']);

        $response = $this->actingAs($unauthorized)->get(route('insurance.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('insurance.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_insurance_audit_passes_cleanly(): void
    {
        $code = Artisan::call('ins:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_ins_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new InsAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('ins:audit');
        $this->assertSame(1, $code);
    }
}
