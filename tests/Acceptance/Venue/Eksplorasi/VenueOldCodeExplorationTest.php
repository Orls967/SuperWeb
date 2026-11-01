<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue\Eksplorasi;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Venue\Application\Actions\AccrueCreatorStreamRoyaltyIdempotentAction;
use Modules\Venue\Application\Actions\ApplyDynamicBottleSurgePricingAction;
use Modules\Venue\Application\Actions\ExecuteArtistContractEscrowAndWhtPayoutAction;
use Modules\Venue\Application\Actions\ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction;
use Modules\Venue\Application\Actions\IssueFestivalTicketWithGaplessHashAction;
use Modules\Venue\Application\Actions\OptimizeGlobalHospitalityEntertainmentPortfolioRevparAction;
use Modules\Venue\Application\Actions\ReconcileLiquorPourSpoutVarianceAction;
use Modules\Venue\Application\Actions\ReleaseCreatorHeldRoyaltyAfterWindowAction;
use Modules\Venue\Application\Actions\ReserveTableWithMinimumSpendDepositAction;
use Modules\Venue\Application\Actions\ScanTicketGateWithAntiPassbackAndAgeCheckAction;
use Modules\Venue\Application\Actions\SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction;
use Modules\Venue\Application\Actions\VerifyBiometricCrowdAccessAndZoneCrushCapacityAction;
use Modules\Venue\Domain\Models\ArtistContract;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Ledger\VenueAccounts;
use Tests\TestCase;

class VenueOldCodeExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_eksplorasi_12_action_lama_venue(): void
    {
        $venue = EntertainmentVenue::create([
            'venue_code' => 'BC-BALI-01',
            'name' => 'Omnia Beach Club Bali',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 2500,
            'min_age_requirement' => 21,
        ]);

        $event = EntertainmentEvent::create([
            'event_code' => 'EVT-SUNSET-01',
            'venue_id' => $venue->id,
            'title' => 'Sunset Festival 2026',
            'doors_open_at' => now()->addDays(5),
            'ticket_floor_price_idr' => 250000,
            'ticket_ceiling_price_idr' => 1500000,
            'current_ticket_price_idr' => 500000,
        ]);

        $zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-VIP-01',
            'venue_id' => $venue->id,
            'name' => 'Main Stage VIP',
            'capacity_limit' => 500,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'contract_number' => 'ART-BC-001',
            'artist_name' => 'DJ Test Headline',
            'advance_amount' => 50000000,
            'door_share_percentage' => '30.00',
            'status' => 'active',
        ]);

        // 1. IssueFestivalTicketWithGaplessHashAction: SEBAGIAN
        // Evaluasi: Menghasilkan tiket, namun sequence dari count() (rawan gap bila delete),
        // hash memakai time() dan bukan CanonicalHashChain, belum ada posting ledger unearned,
        // belum validasi hold kuota, dan belum menerima idempotency key eksplisit.
        $issueAction = app(IssueFestivalTicketWithGaplessHashAction::class);
        $resIssue = $issueAction->handle($event->id, 1, 500000, 'GENERAL_ADMISSION');
        $this->assertNotNull($resIssue['ticket']);
        $this->assertStringStartsWith('TKT-', $resIssue['ticket_number']);

        // 2. ScanTicketGateWithAntiPassbackAndAgeCheckAction: SEBAGIAN
        // Evaluasi: Mencegah tiket status USED, namun tidak memverifikasi HMAC signature QR (V-4),
        // usia hardcoded 21 tanpa cek Party, dan scan gate mengupdate tiket langsung (PR5 append-only terlanggar).
        $scanAction = app(ScanTicketGateWithAntiPassbackAndAgeCheckAction::class);
        $resScan = $scanAction->handle($resIssue['ticket']->id, 25, $zone->id);
        $this->assertSame('ALLOWED', $resScan['status']);

        // 3. ReserveTableWithMinimumSpendDepositAction: SEBAGIAN
        // Evaluasi: Menandai HELD, namun booking code menggunakan acak random_bytes(4) (PR3 determinisme terlanggar),
        // tidak terintegrasi PaymentGateway authorize/hold, dan belum terikat clock untuk pelepasan hold (V-2).
        $reserveAction = app(ReserveTableWithMinimumSpendDepositAction::class);
        $resReserve = $reserveAction->handle($event->id, 1, 'T-01', 5000000, 2000000, 'ven:exp:tbl:1');
        $this->assertSame('HELD', $resReserve['booking']->status);

        // 4. ExecuteArtistContractEscrowAndWhtPayoutAction: SALAH
        // Evaluasi: Menggunakan kalkulasi float untuk persentase bagi hasil dan pajak PPh 23 (U-1),
        // tidak ada posting jurnal ledger untuk pelepasan escrow dan kewajiban pajak.
        $artistAction = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);
        $resArtist = $artistAction->handle((string) $contract->id, 500000000, 200, 'ven:exp:art:1');
        $this->assertSame('settled', $resArtist['contract']->status);

        // 5. ApplyDynamicBottleSurgePricingAction: KERANGKA
        // Evaluasi: Menggunakan float multiplier dan date('YmdH') tanpa ClockInterface.
        $surgeAction = app(ApplyDynamicBottleSurgePricingAction::class);
        $resSurge = $surgeAction->handle('BOT-VODKA-01', 2500000, 85.0, 23);
        $this->assertGreaterThanOrEqual(2500000, $resSurge['final_price_idr']);

        // 6. ReconcileLiquorPourSpoutVarianceAction: KERANGKA
        // Evaluasi: Menggunakan kalkulasi float ml dan date('YmdHis').
        $liquorAction = app(ReconcileLiquorPourSpoutVarianceAction::class);
        $resLiquor = $liquorAction->handle('BOT-VODKA-01', 10, 480.0, 45.0);
        $this->assertNotEmpty($resLiquor['status']);

        // 7. VerifyBiometricCrowdAccessAndZoneCrushCapacityAction: KERANGKA
        $bioAction = app(VerifyBiometricCrowdAccessAndZoneCrushCapacityAction::class);
        $this->assertInstanceOf(VerifyBiometricCrowdAccessAndZoneCrushCapacityAction::class, $bioAction);

        // 8. ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction: SEBAGIAN
        $resaleAction = app(ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction::class);
        $this->assertInstanceOf(ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction::class, $resaleAction);

        // 9. AccrueCreatorStreamRoyaltyIdempotentAction: KERANGKA
        $accrueAction = app(AccrueCreatorStreamRoyaltyIdempotentAction::class);
        $this->assertInstanceOf(AccrueCreatorStreamRoyaltyIdempotentAction::class, $accrueAction);

        // 10. ReleaseCreatorHeldRoyaltyAfterWindowAction: KERANGKA
        $releaseAction = app(ReleaseCreatorHeldRoyaltyAfterWindowAction::class);
        $this->assertInstanceOf(ReleaseCreatorHeldRoyaltyAfterWindowAction::class, $releaseAction);

        // 11. SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction: KERANGKA
        $syndicateAction = app(SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction::class);
        $this->assertInstanceOf(SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction::class, $syndicateAction);

        // 12. OptimizeGlobalHospitalityEntertainmentPortfolioRevparAction: KERANGKA
        $revparAction = app(OptimizeGlobalHospitalityEntertainmentPortfolioRevparAction::class);
        $this->assertInstanceOf(OptimizeGlobalHospitalityEntertainmentPortfolioRevparAction::class, $revparAction);
    }

    public function test_eksplorasi_venue_accounts_dan_audit_command(): void
    {
        // Cek helper VenueAccounts
        $this->assertTrue(class_exists(VenueAccounts::class));
        $definitions = VenueAccounts::definitions();
        $this->assertNotEmpty($definitions);

        // Periksa akun kontrol ven:ticket_unearned:IDR
        $codes = array_column($definitions, 'code');
        $this->assertContains('ven:ticket_unearned:IDR', $codes, 'Akun ven:ticket_unearned:IDR terdaftar di VenueAccounts');

        // Cek ven:audit command (46 baris)
        $exitCode = Artisan::call('ven:audit');
        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Venue Operations & Crowd Safety Audit', $output);
        $this->assertStringContainsString('Venue audit PASSED', $output);
    }

    public function test_eksplorasi_rute_dan_halaman_venue_per_role(): void
    {
        $routes = Route::getRoutes();

        $venueRoutes = [];
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'venue')) {
                $venueRoutes[$route->getName() ?? $uri] = [
                    'methods' => $route->methods(),
                    'uri' => $uri,
                    'middleware' => $route->middleware(),
                ];
            }
        }

        $this->assertNotEmpty($venueRoutes);
        $this->assertArrayHasKey('venue.index', $venueRoutes);
        $this->assertArrayHasKey('venue.ticketing.index', $venueRoutes);
        $this->assertArrayHasKey('venue.ticketing.issue', $venueRoutes);
        $this->assertArrayHasKey('venue.ticketing.gate.scan', $venueRoutes);
        $this->assertArrayHasKey('venue.tables.index', $venueRoutes);
        $this->assertArrayHasKey('venue.tables.reserve', $venueRoutes);
        $this->assertArrayHasKey('venue.artist.index', $venueRoutes);
        $this->assertArrayHasKey('venue.artist.settle', $venueRoutes);
    }
}
