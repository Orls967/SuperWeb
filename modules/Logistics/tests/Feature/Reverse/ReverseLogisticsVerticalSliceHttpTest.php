<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature\Reverse;

use App\Models\User;
use App\Quality\Audit\Fixtures\LogisticsCircularCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Application\Services\Reverse\ReverseLogisticsService;
use Modules\Logistics\Domain\Models\Reverse\ReverseOrder;
use Tests\TestCase;

class ReverseLogisticsVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected ReverseLogisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(ReverseLogisticsService::class);
    }

    public function test_a_authorized_user_can_access_reverse_index_and_process_intake(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        // 1. Access reverse index
        $response = $this->actingAs($dispatcher)->get(route('logistics.reverse.index'));
        $response->assertOk();
        $response->assertSeeText('Reverse Logistics & Circular Economy Engine');
        $response->assertSeeText('Appraised Circular Value');

        // 2. Submit new reverse order via POST
        $createResponse = $this->actingAs($dispatcher)->post(route('logistics.reverse.store'), [
            'event_trigger_type' => 'resto.waste_bulk',
            'source_reference_id' => 'BATCH-TEST-HTTP-01',
            'pickup_location' => 'Central Kitchen Resto Hub',
            'destination_facility' => 'Biodiesel Refinery Cikarang',
            'items' => [
                [
                    'category' => 'COOKING_OIL_JELANTAH',
                    'description' => 'Minyak jelantah resto',
                    'quantity' => 150.0,
                    'uom' => 'LITER',
                    'recycling_target' => 'BIODIESEL_REFINERY',
                    'appraised_unit_price_idr' => 8500,
                ],
            ],
        ]);

        $createResponse->assertRedirect(route('logistics.reverse.index'));
        $createResponse->assertSessionHas('success');

        $order = ReverseOrder::where('source_reference_id', 'BATCH-TEST-HTTP-01')->firstOrFail();
        $this->assertEquals('SCHEDULED', $order->status);
        $this->assertEquals(1275000, $order->total_appraised_value_idr);

        // 3. Process circular intake via POST
        $intakeResponse = $this->actingAs($dispatcher)->post(route('logistics.reverse.intake', $order));
        $intakeResponse->assertRedirect(route('logistics.reverse.index'));
        $intakeResponse->assertSessionHas('success');
        $this->assertEquals('PROCESSED', $order->refresh()->status);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('logistics.reverse.index'));
        $response->assertForbidden();

        $postResponse = $this->actingAs($customer)->post(route('logistics.reverse.store'), [
            'event_trigger_type' => 'resto.waste_bulk',
            'source_reference_id' => 'FORBIDDEN-01',
            'pickup_location' => 'Nowhere',
            'destination_facility' => 'Nowhere',
            'items' => [
                [
                    'category' => 'COOKING_OIL_JELANTAH',
                    'description' => 'Jelantah',
                    'quantity' => 10.0,
                    'uom' => 'LITER',
                    'recycling_target' => 'BIODIESEL',
                    'appraised_unit_price_idr' => 1000,
                ],
            ],
        ]);
        $postResponse->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('logistics.reverse.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_reverse_audit_passes_cleanly(): void
    {
        $items = [
            [
                'category' => 'COOKING_OIL_JELANTAH',
                'description' => 'Used cooking oil for biodiesel',
                'quantity' => 100.0,
                'uom' => 'LITER',
                'recycling_target' => 'BIODIESEL_REFINERY',
                'appraised_unit_price_idr' => 8500,
            ],
        ];

        $order = $this->service->triggerReverseOrderFromEvent(
            eventTriggerType: 'resto.waste_bulk',
            sourceReferenceId: 'BATCH-AUDIT-CLEAN-01',
            pickupLocation: 'Kitchen A',
            destinationFacility: 'Plant B',
            itemsData: $items
        );

        $this->service->processCircularIntake($order);

        $this->artisan('lgx:audit-circular')
            ->assertSuccessful()
            ->expectsOutputToContain('0 selisih');
    }

    public function test_e_lgx_audit_circular_fails_when_data_is_corrupted(): void
    {
        $fixture = new LogisticsCircularCorruptionFixture;
        $corruptKeyword = $fixture->corrupt();

        $this->artisan('lgx:audit-circular')
            ->assertFailed()
            ->expectsOutputToContain($corruptKeyword);
    }
}
