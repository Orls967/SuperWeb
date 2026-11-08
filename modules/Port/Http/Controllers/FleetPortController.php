<?php

declare(strict_types=1);

namespace Modules\Port\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Application\Services\GeneralAverageAdjustmentService;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\MaritimeEsgCiiService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Domain\Models\FleetCharterParty;
use Modules\Port\Domain\Models\FleetDemurrageInvoice;
use Modules\Port\Domain\Models\FleetGeneralAverageCase;
use Modules\Port\Domain\Models\FleetNoonReport;
use Modules\Port\Domain\Models\FleetPortCall;
use Modules\Port\Domain\Models\FleetPortDisbursement;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Shared\Domain\Algorithms\Port\BunkerFuelConsumptionOptimizer;
use Modules\Shared\Domain\Algorithms\Port\TimeCharterEquivalentCalculator;

final class FleetPortController extends Controller
{
    public function __construct(
        private readonly PortFleetService $fleetService,
        private readonly VoyageExecutionService $voyageService,
        private readonly CommercialCharterService $charterService,
        private readonly MarineServicesDisbursementService $marineService,
        private readonly MaritimeEsgCiiService $esgService,
        private readonly GeneralAverageAdjustmentService $gaService,
        private readonly PortFleetLedgerService $ledgerService,
        private readonly BunkerFuelConsumptionOptimizer $bunkerOptimizer = new BunkerFuelConsumptionOptimizer(),
        private readonly TimeCharterEquivalentCalculator $tceCalculator = new TimeCharterEquivalentCalculator()
    ) {}

    public function index(): View
    {
        $vessels = FleetVessel::with(['classification', 'certificates', 'bunkerTanks'])->get();
        $activeVoyages = FleetVoyage::with(['vessel', 'charterParty'])->latest()->take(10)->get();

        $ciiSummaries = [];
        foreach ($vessels as $vessel) {
            $ciiSummaries[$vessel->id] = $this->esgService->auditVesselCii((int) $vessel->id);
        }

        return view('port::fleet.index', [
            'vessels' => $vessels,
            'activeVoyages' => $activeVoyages,
            'ciiSummaries' => $ciiSummaries,
        ]);
    }

    public function voyages(): View
    {
        $voyages = FleetVoyage::with(['vessel', 'legs', 'noonReports', 'portCalls'])->latest()->paginate(15);
        $recentReports = FleetNoonReport::with(['voyage', 'vessel'])->latest('report_date')->take(10)->get();

        return view('port::fleet.voyages', [
            'voyages' => $voyages,
            'recentReports' => $recentReports,
        ]);
    }

    public function charter(): View
    {
        $charterParties = FleetCharterParty::with(['vessel', 'freightContracts', 'laytimeCalculations', 'offHireEvents'])->latest()->paginate(15);
        $unpaidInvoices = FleetDemurrageInvoice::where('status', 'UNPAID')->get();

        return view('port::fleet.charter', [
            'charterParties' => $charterParties,
            'unpaidInvoices' => $unpaidInvoices,
        ]);
    }

    public function services(): View
    {
        $portCalls = FleetPortCall::with(['voyage', 'pilotageRequests', 'tugServices', 'berthBookings', 'disbursements', 'bunkeringOperations'])->latest()->paginate(15);
        $disbursements = FleetPortDisbursement::latest()->take(15)->get();

        return view('port::fleet.services', [
            'portCalls' => $portCalls,
            'disbursements' => $disbursements,
        ]);
    }

    /**
     * Endpoint API kalkulator simulasi bunker & TCE.
     */
    public function simulateTce(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cargo_quantity_mt' => 'required|numeric|min:100',
            'freight_rate_usd_mt' => 'required|numeric|min:1',
            'sea_steaming_days' => 'required|numeric|min:0.5',
            'port_days_load' => 'required|numeric|min:0.5',
            'port_days_disch' => 'required|numeric|min:0.5',
            'bunker_sea_cost_usd' => 'required|numeric|min:0',
            'bunker_port_cost_usd' => 'required|numeric|min:0',
            'port_disbursements_usd' => 'required|numeric|min:0',
        ]);

        $tce = $this->tceCalculator->calculateTce(
            (float) $validated['cargo_quantity_mt'],
            (float) $validated['freight_rate_usd_mt'],
            (float) $validated['sea_steaming_days'],
            (float) $validated['port_days_load'],
            (float) $validated['port_days_disch'],
            (float) $validated['bunker_sea_cost_usd'],
            (float) $validated['bunker_port_cost_usd'],
            (float) $validated['port_disbursements_usd']
        );

        return response()->json($tce);
    }

    /**
     * Endpoint Simulasi Insiden Maritim Selat Malaka (T1 Simulation Scenario).
     * Mensimulasikan badai monsun, deviasi rute keselamatan, laytime sengketa, dan deklarasi GA.
     */
    public function simulateMalaccaIncident(Request $request): JsonResponse
    {
        $vessel = FleetVessel::first();
        if (! $vessel) {
            return response()->json(['error' => 'Belum ada kapal di armada untuk simulasi'], 422);
        }

        // 1. Buat pelayaran darurat Selat Malaka
        $voyage = $this->voyageService->planVoyage([
            'voyage_code' => 'VOY-MALACCA-' . bin2hex(random_bytes(2)),
            'vessel_id' => $vessel->id,
            'voyage_number' => 'VOY-2026-M01',
            'origin_port' => 'IDJKT',
            'destination_port' => 'SGSIN',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Jurong Port Singapore',
            'planned_start_at' => CarbonImmutable::now(),
            'planned_end_at' => CarbonImmutable::now()->addDays(4),
            'planned_distance_nm' => 520.0,
        ]);

        // 2. Evaluasi advisory cuaca buruk Selat Malaka (Wave height 4.2m, deviasi 35 nm)
        $advisory = $this->voyageService->evaluateWeatherAdvisory(
            (int) $voyage->id,
            'SUMATRA_SQUALL_MALACCA_STRAIT',
            4.20,
            2.50,
            35.00
        );
        $this->voyageService->acceptWeatherDiversion((int) $advisory->id);

        // 3. Deklarasikan General Average York-Antwerp Rules
        $gaCase = $this->gaService->declareAndAdjustGeneralAverage(
            (int) $voyage->id,
            'GA-MALACCA-' . strtoupper(bin2hex(random_bytes(3))),
            'Kapal terpaksa deviasi dan meminta bantuan salvage tug akibat badai Sumatra Squall di perairan Selat Malaka',
            'Richards Hogg Lindley Average Adjusters',
            18500000.0, // Sound value kapal
            120000.0,   // Hull damage
            14200000.0, // Nilai komersial muatan
            80000.0,    // Cargo wetting damage
            350000.0,   // Freight at risk
            25000.0,    // Expenses saved
            [
                ['interest' => 'SHIP', 'description' => 'Damage to propeller during emergency maneuvering', 'amount_usd' => 85000.0, 'category' => 'SACRIFICE'],
                ['interest' => 'CARGO', 'description' => 'Jettison of deck cargo to prevent capsize', 'amount_usd' => 140000.0, 'category' => 'SACRIFICE'],
                ['interest' => 'SHIP', 'description' => 'Salvage tug assistance under LOF contract', 'amount_usd' => 220000.0, 'category' => 'EXPENDITURE'],
                ['interest' => 'SHIP', 'description' => 'Port of refuge port dues and agency at Batam', 'amount_usd' => 45000.0, 'category' => 'EXPENDITURE'],
            ]
        );

        // 4. Catat transaksi di double-entry ledger
        $this->ledgerService->recordBalancedTransaction(
            (int) $voyage->id,
            (int) $vessel->id,
            PortFleetLedgerService::ACC_EXPENSE_GENERAL_AVERAGE,
            PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
            (float) $gaCase->ship_contribution_usd,
            'GENERAL_AVERAGE_DECLARATION',
            (int) $gaCase->id,
            "Alokasi kontribusi General Average York-Antwerp untuk Kasus {$gaCase->case_number}"
        );

        return response()->json([
            'status' => 'SIMULATION_SUCCESS',
            'voyage' => $voyage,
            'weather_advisory' => $advisory,
            'general_average_case' => $gaCase,
        ]);
    }
}

