<?php

declare(strict_types=1);

namespace Tests\Feature\Venue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Venue\Application\Services\VenueCreatorEconomyService;
use Modules\Venue\Domain\Models\Creator;
use Modules\Venue\Domain\Models\RightsContract;
use RuntimeException;
use Tests\TestCase;

class CreatorRoyaltyPayoutGuardTest extends TestCase
{
    use RefreshDatabase;

    protected VenueCreatorEconomyService $service;

    protected Creator $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $ledgerMock = $this->createMock(\Modules\Banking\Application\Services\LedgerService::class);
        $this->app->instance(\Modules\Banking\Application\Services\LedgerService::class, $ledgerMock);

        $this->service = app(VenueCreatorEconomyService::class);
        $this->creator = $this->service->registerCreator('DJ Nusantara Beat', 'ELECTRONIC');
    }

    public function test_payout_rejected_when_hold_window_has_not_passed(): void
    {
        // Kontrak dibuat sekarang dengan hold window 14 hari
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);
        $this->service->accrueStreamRevenue($contract, 10_000_000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payout hold active: Cannot release royalties before claim dispute window of 14 days expires');

        // Parameter boolean caller ditiadakan; verifikasi berbasis waktu nyata tersimpan
        $this->service->releaseRoyaltyPayout($contract);
    }

    public function test_payout_succeeds_when_hold_window_has_passed(): void
    {
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);
        $this->service->accrueStreamRevenue($contract, 10_000_000);

        // Simulasikan waktu kontrak sudah lewat 15 hari
        $contract->created_at = now()->subDays(15);
        $contract->save();

        $paid = $this->service->releaseRoyaltyPayout($contract);

        $this->assertSame(7_000_000, $paid);
        $this->assertSame(0, (int) $contract->fresh()->held_royalties_idr);
        $this->assertSame(7_000_000, (int) $contract->fresh()->paid_royalties_idr);
    }

    public function test_payout_rejected_when_contract_is_disputed(): void
    {
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);
        $this->service->accrueStreamRevenue($contract, 10_000_000);

        // Window sudah lewat tetapi status DISPUTED
        $contract->created_at = now()->subDays(20);
        $contract->status = 'DISPUTED';
        $contract->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payout hold active: Cannot release royalties while contract');

        $this->service->releaseRoyaltyPayout($contract);
    }

    public function test_payout_returns_zero_when_no_held_royalties(): void
    {
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);
        $contract->created_at = now()->subDays(15);
        $contract->save();

        $paid = $this->service->releaseRoyaltyPayout($contract);

        $this->assertSame(0, $paid);
    }
}
