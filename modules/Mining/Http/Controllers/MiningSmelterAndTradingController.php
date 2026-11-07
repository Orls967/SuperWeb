<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Mining\Application\Actions\AbortSmelterFurnaceRunAction;
use Modules\Mining\Application\Actions\AllocateSmelterJointCostsAction;
use Modules\Mining\Application\Actions\AuditMiningMetalsDeskAction;
use Modules\Mining\Application\Actions\AuditSmelterChainOfCustodyAction;
use Modules\Mining\Application\Actions\CalculateSmelterCarbonTaxLiabilityAction;
use Modules\Mining\Application\Actions\CalculateSmelterSlagBasicityAction;
use Modules\Mining\Application\Actions\ConductSmelterQualityAssayAction;
use Modules\Mining\Application\Actions\CreateMetalsTradingContractAction;
use Modules\Mining\Application\Actions\CreateSmelterFurnaceRunAction;
use Modules\Mining\Application\Actions\DispatchSmelterByproductSlagAction;
use Modules\Mining\Application\Actions\EvaluateLmeContangoBackwardationAction;
use Modules\Mining\Application\Actions\EvaluateSmelterEnergyEmissionIntensityAction;
use Modules\Mining\Application\Actions\ExecuteLmeMarkToMarketValuationAction;
use Modules\Mining\Application\Actions\ExecuteSmelterEnergyBalanceAction;
use Modules\Mining\Application\Actions\ExportSmelterTradingReportCsvAction;
use Modules\Mining\Application\Actions\IssueMetalsWarehouseReceiptAction;
use Modules\Mining\Application\Actions\LockMetalsTradingContractPriceAction;
use Modules\Mining\Application\Actions\PlanLayeredMetalsHedgingAction;
use Modules\Mining\Application\Actions\PledgeWarehouseReceiptBankScfAction;
use Modules\Mining\Application\Actions\ReconcileSmelterMassBalanceAction;
use Modules\Mining\Application\Actions\RecordFurnaceTappingOutputAction;
use Modules\Mining\Application\Actions\RecycleSmelterByproductSlagAction;
use Modules\Mining\Application\Actions\RegisterWarehouseReceiptBappebtiAction;
use Modules\Mining\Application\Actions\ReleaseWarehouseReceiptCollateralAction;
use Modules\Mining\Application\Actions\RunMetalsHilirisasiPipelineAction;
use Modules\Mining\Application\Actions\ScanExpiredWarehouseReceiptsAction;
use Modules\Mining\Application\Actions\SettleFinalAssayInvoiceAction;
use Modules\Mining\Application\Actions\SettleMetalsTradingContractAction;
use Modules\Mining\Application\Actions\TriggerMetalsTradingMarginCallAction;
use Modules\Mining\Application\Actions\ValidateWarehouseReceiptIntegrityAction;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\Models\MiningMetalsTradingPosition;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\Models\MiningSmelterByproduct;
use Modules\Mining\Domain\Models\MiningSmelterEnergyEmission;
use Modules\Mining\Domain\Models\MiningSmelterFurnace;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Domain\Models\MiningSmelterQualityAssay;
use Modules\Mining\Http\Requests\CreateMetalsContractRequest;
use Modules\Mining\Http\Requests\CreateSmelterFurnaceRunRequest;
use Modules\Mining\Http\Requests\DispatchByproductSlagRequest;
use Modules\Mining\Http\Requests\ExecuteSmelterBalanceRequest;
use Modules\Mining\Http\Requests\IssueWarehouseReceiptRequest;
use Modules\Shared\Domain\Algorithms\MetalsTrading\AssaySplitComparison;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredHedgeHorizonQuote;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointCostInput;

class MiningSmelterAndTradingController extends Controller
{
    public function smelterView(): Response
    {
        $furnaces = MiningSmelterFurnace::query()->latest()->get();
        $furnaceRuns = MiningSmelterFurnaceRun::query()->with(['furnace', 'energyEmissions', 'qualityAssays'])->latest()->take(15)->get();
        $emissions = MiningSmelterEnergyEmission::query()->latest()->take(10)->get();

        return response()->view('mining::smelter', [
            'furnaces' => $furnaces,
            'furnaceRuns' => $furnaceRuns,
            'emissions' => $emissions,
        ]);
    }

    public function tradingView(): Response
    {
        $contracts = MiningMetalsTradingContract::query()->with('positions')->latest()->paginate(15);
        $positions = MiningMetalsTradingPosition::query()->latest('valuation_date')->take(10)->get();

        return response()->view('mining::trading', [
            'contracts' => $contracts,
            'positions' => $positions,
        ]);
    }

    public function warehouseReceiptView(): Response
    {
        $receipts = MiningMetalsWarehouseReceipt::query()->with('assay')->latest()->paginate(15);
        $assays = MiningSmelterQualityAssay::query()->latest()->take(10)->get();

        return response()->view('mining::warehouse_receipt', [
            'receipts' => $receipts,
            'assays' => $assays,
        ]);
    }

    public function byproductView(): Response
    {
        $byproducts = MiningSmelterByproduct::query()->with('furnaceRun')->latest()->paginate(15);

        return response()->view('mining::byproduct', [
            'byproducts' => $byproducts,
        ]);
    }

    public function createFurnaceRun(CreateSmelterFurnaceRunRequest $request, CreateSmelterFurnaceRunAction $action): JsonResponse
    {
        $furnaceId = (string) $request->input('furnace_id');
        $run = $action->execute($furnaceId, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $run,
            'message' => 'Batch tanur smelter berhasil dijadwalkan.',
        ], 201);
    }

    public function executeEnergyBalance(ExecuteSmelterBalanceRequest $request, ExecuteSmelterEnergyBalanceAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $powerMwh = $request->input('power_consumption_mwh') ? (float) $request->input('power_consumption_mwh') : null;
        $result = $action->execute($run, $powerMwh);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
            'message' => 'Neraca massa & energi pyrometallurgical selesai dihitung.',
        ]);
    }

    public function recordTapping(Request $request, RecordFurnaceTappingOutputAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $metalTons = (float) $request->input('metal_output_tons', 18.5);
        $slagTons = (float) $request->input('slag_output_tons', 62.0);
        $temp = (float) $request->input('tapping_temperature_c', 1520.0);

        $updated = $action->execute($run, $metalTons, $slagTons, $temp);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => 'Penuangan logam dan terak (tapping) berhasil dicatat dan dikapitalisasi.',
        ]);
    }

    public function conductQualityAssay(Request $request, ConductSmelterQualityAssayAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $assay = $action->execute($run, $request->all());

        return response()->json([
            'success' => true,
            'data' => $assay,
            'message' => 'Sertifikasi pengujian assay laboratorium mutu logam berhasil diterbitkan.',
        ]);
    }

    public function evaluateEmissionIntensity(Request $request, EvaluateSmelterEnergyEmissionIntensityAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $evaluation = $action->execute($run);

        return response()->json([
            'success' => true,
            'data' => $evaluation,
            'message' => 'Evaluasi intensitas emisi GRK Scope 1 & 2 berhasil.',
        ]);
    }

    public function createTradingContract(CreateMetalsContractRequest $request, CreateMetalsTradingContractAction $action): JsonResponse
    {
        $contract = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => $contract,
            'message' => 'Kontrak perdagangan logam berhasil dibuat.',
        ], 201);
    }

    public function lockContractPrice(Request $request, LockMetalsTradingContractPriceAction $action): JsonResponse
    {
        $contract = MiningMetalsTradingContract::findOrFail((string) $request->input('contract_id'));
        $price = $request->input('price_cents') ? (int) $request->input('price_cents') : null;
        $updated = $action->execute($contract, $price);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => 'Harga LME kontrak perdagangan berhasil dikunci.',
        ]);
    }

    public function executeMtmValuation(Request $request, ExecuteLmeMarkToMarketValuationAction $action): JsonResponse
    {
        $contract = MiningMetalsTradingContract::findOrFail((string) $request->input('contract_id'));
        $type = (string) $request->input('position_type', 'SHORT');
        $price = $request->input('market_price_usd_cents') ? (int) $request->input('market_price_usd_cents') : null;

        $result = $action->execute($contract, $type, $price);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
            'message' => 'Valuasi harian Mark-to-Market berhasil dihitung dan diselesaikan.',
        ]);
    }

    public function triggerMarginCall(Request $request, TriggerMetalsTradingMarginCallAction $action): JsonResponse
    {
        $contract = MiningMetalsTradingContract::findOrFail((string) $request->input('contract_id'));
        $alert = $action->execute($contract);

        return response()->json([
            'success' => true,
            'data' => $alert,
            'message' => 'Pemeriksaan status panggilan margin selesai.',
        ]);
    }

    public function settleContract(Request $request, SettleMetalsTradingContractAction $action): JsonResponse
    {
        $contract = MiningMetalsTradingContract::findOrFail((string) $request->input('contract_id'));
        $isPhysical = (bool) $request->input('is_physical_delivery', true);
        $settled = $action->execute($contract, $isPhysical);

        return response()->json([
            'success' => true,
            'data' => $settled,
            'message' => 'Kontrak perdagangan logam berhasil diselesaikan.',
        ]);
    }

    public function issueWarehouseReceipt(IssueWarehouseReceiptRequest $request, IssueMetalsWarehouseReceiptAction $action): JsonResponse
    {
        $assay = MiningSmelterQualityAssay::findOrFail((string) $request->input('assay_id'));
        $weight = (float) $request->input('certified_weight_metric_tons');
        $receipt = $action->execute($assay, $weight, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $receipt,
            'message' => 'Resi gudang digital beragun pembiayaan berhasil diterbitkan.',
        ], 201);
    }

    public function registerBappebti(Request $request, RegisterWarehouseReceiptBappebtiAction $action): JsonResponse
    {
        $receipt = MiningMetalsWarehouseReceipt::findOrFail((string) $request->input('receipt_id'));
        $registered = $action->execute($receipt);

        return response()->json([
            'success' => true,
            'data' => $registered,
            'message' => 'Resi gudang berhasil didaftarkan secara resmi di Bappebti / ISWARE.',
        ]);
    }

    public function pledgeBankScf(Request $request, PledgeWarehouseReceiptBankScfAction $action): JsonResponse
    {
        $receipt = MiningMetalsWarehouseReceipt::findOrFail((string) $request->input('receipt_id'));
        $bankName = (string) $request->input('bank_name', 'PT Bank Mandiri (Persero) Tbk');
        $loanAmount = $request->input('loan_amount_idr') ? (int) $request->input('loan_amount_idr') : null;

        $pledged = $action->execute($receipt, $bankName, $loanAmount);

        return response()->json([
            'success' => true,
            'data' => $pledged,
            'message' => 'Resi gudang berhasil dijaminkan ke bank mitra SCF dan fasilitas pinjaman dicairkan.',
        ]);
    }

    public function releaseWarehouseReceipt(Request $request, ReleaseWarehouseReceiptCollateralAction $action): JsonResponse
    {
        $receipt = MiningMetalsWarehouseReceipt::findOrFail((string) $request->input('receipt_id'));
        $isFull = (bool) $request->input('is_full_release', true);
        $repayAmount = $request->input('repayment_amount_idr') ? (int) $request->input('repayment_amount_idr') : null;

        $released = $action->execute($receipt, $isFull, $repayAmount);

        return response()->json([
            'success' => true,
            'data' => $released,
            'message' => 'Pelepasan jaminan fidusia resi gudang berhasil diproses.',
        ]);
    }

    public function dispatchByproductSlag(DispatchByproductSlagRequest $request, DispatchSmelterByproductSlagAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $tons = (float) $request->input('dispatch_tons');
        $byproduct = $action->execute($run, $tons, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $byproduct,
            'message' => 'Pengiriman produk sampingan terak nikel slag berhasil dicatat dan dimanifestasikan.',
        ]);
    }

    public function recycleByproductSlag(Request $request, RecycleSmelterByproductSlagAction $action): JsonResponse
    {
        $byproduct = MiningSmelterByproduct::findOrFail((string) $request->input('byproduct_id'));
        $tons = (float) $request->input('recycle_tons', 10.0);
        $app = (string) $request->input('circular_application', 'Geopolymer Concrete Subbase');

        $recycled = $action->execute($byproduct, $tons, $app);

        return response()->json([
            'success' => true,
            'data' => $recycled,
            'message' => 'Pemanfaatan sirkular terak slag berhasil dicatat.',
        ]);
    }

    public function reconcileMassBalance(Request $request, ReconcileSmelterMassBalanceAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $reconciliation = $action->execute($run);

        return response()->json([
            'success' => true,
            'data' => $reconciliation,
            'message' => 'Rekonsiliasi neraca massa tanur selesai.',
        ]);
    }

    public function auditChainOfCustody(Request $request, AuditSmelterChainOfCustodyAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $audit = $action->execute($run);

        return response()->json([
            'success' => true,
            'data' => $audit,
            'message' => 'Audit rantai kustodi ketertelusuran produk selesai.',
        ]);
    }

    public function calculateCarbonTax(Request $request, CalculateSmelterCarbonTaxLiabilityAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $tax = $action->execute($run);

        return response()->json([
            'success' => true,
            'data' => $tax,
            'message' => 'Perhitungan liabilitas pajak karbon selesai.',
        ]);
    }

    public function calculateSlagBasicity(Request $request, CalculateSmelterSlagBasicityAction $action): JsonResponse
    {
        $sio2 = (float) $request->input('sio2_pct', 42.0);
        $mgo = (float) $request->input('mgo_pct', 22.0);
        $cao = (float) $request->input('cao_pct', 2.5);

        $basicity = $action->execute($sio2, $mgo, $cao);

        return response()->json([
            'success' => true,
            'data' => $basicity,
            'message' => 'Perhitungan rasio basicity terak selesai.',
        ]);
    }

    public function evaluateLmeContango(Request $request, EvaluateLmeContangoBackwardationAction $action): JsonResponse
    {
        $metal = (string) $request->input('metal_symbol', 'LME-NI');
        $spread = $action->execute($metal);

        return response()->json([
            'success' => true,
            'data' => $spread,
            'message' => 'Evaluasi struktur kurva forward LME selesai.',
        ]);
    }

    public function validateReceiptIntegrity(Request $request, ValidateWarehouseReceiptIntegrityAction $action): JsonResponse
    {
        $receipt = MiningMetalsWarehouseReceipt::findOrFail((string) $request->input('receipt_id'));
        $val = $action->execute($receipt);

        return response()->json([
            'success' => true,
            'data' => $val,
            'message' => 'Validasi integritas segel digital selesai.',
        ]);
    }

    public function abortFurnaceRun(Request $request, AbortSmelterFurnaceRunAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $reason = (string) $request->input('reason', 'Emergency Abort Tanur');
        $aborted = $action->execute($run, $reason);

        return response()->json([
            'success' => true,
            'data' => $aborted,
            'message' => 'Siklus batch tanur smelter berhasil dibatalkan secara aman.',
        ]);
    }

    public function exportReportCsv(ExportSmelterTradingReportCsvAction $action): Response
    {
        $result = $action->execute();

        return response($result['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$result['filename']}\"",
        ]);
    }

    public function runHilirisasiPipeline(Request $request, RunMetalsHilirisasiPipelineAction $action): JsonResponse
    {
        $run = MiningSmelterFurnaceRun::findOrFail((string) $request->input('run_id'));
        $contractId = $request->input('contract_id');
        $contract = $contractId ? MiningMetalsTradingContract::find($contractId) : null;

        $result = $action->execute($run, $contract, $request->all());

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['success'] ? 'Pipeline hilirisasi mineral berhasil dijalankan.' : 'Pipeline hilirisasi mineral mengalami kendala dan dikompensasi.',
        ], $result['success'] ? 200 : 422);
    }

    public function planLayeredHedge(Request $request, PlanLayeredMetalsHedgingAction $action): JsonResponse
    {
        $quotes = [
            new LayeredHedgeHorizonQuote(
                monthTenor: (string) $request->input('month', date('Y-m')),
                plannedProductionTons: (float) $request->input('planned_tons', 1000.0),
                existingHedgedTons: (float) $request->input('existing_hedged_tons', 200.0),
                targetHedgeRatio: (float) $request->input('target_ratio', 0.60),
                lmeForwardPriceUsdPerTon: (int) $request->input('forward_price_usd', 18500),
                exchangeRateIdrPerUsd: (int) $request->input('usd_idr_rate', 16100)
            ),
        ];

        $shock = (float) $request->input('shock_pct', 0.20);
        $result = $action->execute($quotes, $shock);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
            'message' => 'Optimasi portofolio lindung nilai bertingkat M13 berhasil.',
        ]);
    }

    public function allocateJointCost(Request $request, AllocateSmelterJointCostsAction $action): JsonResponse
    {
        $runCode = (string) $request->input('run_code', 'RUN-DEMO-01');
        $input = new SmelterJointCostInput(
            jointSmeltingCostIdr: (int) $request->input('joint_cost_idr', 1500000000),
            primaryMetalOutputTons: (float) $request->input('primary_tons', 18.0),
            primaryMetalMarketPriceIdr: (int) $request->input('primary_price_idr', 290000000),
            granulatedSlagOutputTons: (float) $request->input('slag_tons', 80.0),
            granulatedSlagMarketPriceIdr: (int) $request->input('slag_price_idr', 450000),
            crudeSulfurOutputTons: (float) $request->input('sulfur_tons', 2.5),
            crudeSulfurMarketPriceIdr: (int) $request->input('sulfur_price_idr', 1800000),
            metalFlueDustOutputTons: (float) $request->input('dust_tons', 1.0),
            metalFlueDustMarketPriceIdr: (int) $request->input('dust_price_idr', 900000)
        );

        $result = $action->execute($runCode, $input);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
            'message' => 'Alokasi biaya bersama produk smelter NRV M14 berhasil diposting.',
        ]);
    }

    public function settleFinalAssay(Request $request, SettleFinalAssayInvoiceAction $action): JsonResponse
    {
        $lotNumber = (string) $request->input('lot_number', 'LOT-DEMO-01');
        $comparison = new AssaySplitComparison(
            lotNetWeightTons: (float) $request->input('net_weight_tons', 100.0),
            sellerNiGradePct: (float) $request->input('seller_grade', 11.5),
            buyerNiGradePct: (float) $request->input('buyer_grade', 11.2),
            splittingLimitPct: (float) $request->input('splitting_limit', 0.15),
            umpireNiGradePct: $request->has('umpire_grade') ? (float) $request->input('umpire_grade') : null,
            lmeOfficialPriceUsdPerTon: (int) $request->input('lme_price_usd', 18500),
            provisionalPaymentRateBps: (int) $request->input('provisional_rate_bps', 8500),
            exchangeRateIdrPerUsd: (int) $request->input('usd_idr_rate', 16100),
            umpireServiceFeeIdr: (int) $request->input('umpire_fee_idr', 40000000)
        );

        $result = $action->execute($lotNumber, $comparison);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
            'message' => 'Settlement invoice final assay dan alokasi wasit M11 berhasil.',
        ]);
    }

    public function scanReceiptExpiry(Request $request, ScanExpiredWarehouseReceiptsAction $action): JsonResponse
    {
        $refDate = $request->input('date');
        $result = $action->execute($refDate ? (string) $refDate : null);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Pemindaian kedaluwarsa resi gudang selesai.',
        ]);
    }

    public function auditMetalsDesk(AuditMiningMetalsDeskAction $action): JsonResponse
    {
        $report = $action->execute();

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Audit kepatuhan metals desk D121-8 selesai dijalankan.',
        ]);
    }
}
