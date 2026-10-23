<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\TradeFinance\Application\Services\CrossBorder\CrossBorderClearingService;
use Modules\TradeFinance\Domain\Models\CrossBorder\CbamCertificate;
use Modules\TradeFinance\Domain\Models\CrossBorder\CrossBorderEscrow;
use Throwable;

class CrossBorderController extends Controller
{
    public function __construct(
        private readonly CrossBorderClearingService $service
    ) {}

    public function index(): View
    {
        return view('trade_finance::crossborder', [
            'escrows' => CrossBorderEscrow::orderByDesc('created_at')->limit(50)->get(),
            'cbamCertificates' => CbamCertificate::orderByDesc('created_at')->limit(50)->get(),
            'escrowSummary' => $this->service->getEscrowCorridorSummary(),
            'cbamSummary' => $this->service->getCbamComplianceSummary(),
        ]);
    }

    public function deposit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'importer_id' => 'required|integer|min:1',
            'exporter_id' => 'required|integer|min:1',
            'amount_cents' => 'required|integer|min:100',
            'locked_fx_rate_idr' => 'required|integer|min:1000',
            'bl_number' => 'required|string|max:64',
            'bl_document' => 'required|string|max:255',
        ]);

        try {
            $escrow = $this->service->depositEscrow(
                importerId: (int) $data['importer_id'],
                exporterId: (int) $data['exporter_id'],
                amountCents: (int) $data['amount_cents'],
                lockedFxRateIdr: (int) $data['locked_fx_rate_idr'],
                blNumber: $data['bl_number'],
                blDocument: $data['bl_document']
            );

            return back()->with('success', "Deposit stablecoin escrow {$escrow->escrow_code} berhasil diproses untuk BL {$escrow->bl_number}.");
        } catch (Throwable $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }
    }

    public function release(Request $request, CrossBorderEscrow $escrow): RedirectResponse
    {
        $data = $request->validate([
            'pod_hash' => 'required|string|max:64',
        ]);

        try {
            $released = $this->service->releaseEscrowOnDelivery($escrow, $data['pod_hash']);

            return back()->with('success', "Escrow {$released->escrow_code} berhasil dirilis ke eksportir berdasarkan verifikasi POD chain.");
        } catch (Throwable $e) {
            return back()->withErrors(['release' => $e->getMessage()]);
        }
    }

    public function dispute(Request $request, CrossBorderEscrow $escrow): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $disputed = $this->service->holdDispute($escrow, $data['reason']);

            return back()->with('success', "Escrow {$disputed->escrow_code} dimasukkan ke dispute hold (24 jam) untuk investigasi four-eyes.");
        } catch (Throwable $e) {
            return back()->withErrors(['dispute' => $e->getMessage()]);
        }
    }

    public function resolveDispute(Request $request, CrossBorderEscrow $escrow): RedirectResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:REFUND,PROCEED',
        ]);

        try {
            $resolved = $this->service->resolveDispute($escrow, $data['decision']);

            return back()->with('success', "Dispute escrow {$resolved->escrow_code} diselesaikan dengan keputusan: {$data['decision']}.");
        } catch (Throwable $e) {
            return back()->withErrors(['resolve' => $e->getMessage()]);
        }
    }

    public function issueCbam(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'container_number' => 'required|string|max:32',
            'origin_factory_code' => 'required|string|max:32',
            'commodity_code' => 'required|string|max:32',
            'net_mass_tons' => 'required|numeric|min:0.01',
            'embedded_emissions_tco2' => 'required|numeric|min:0.01',
            'eur_to_idr_rate' => 'nullable|integer|min:1000',
            'cbam_price_eur_per_ton' => 'nullable|integer|min:1',
            'carbon_credit_ref' => 'nullable|string|max:64',
        ]);

        try {
            $cert = $this->service->issueCbamCertificate(
                containerNumber: $data['container_number'],
                originFactoryCode: $data['origin_factory_code'],
                commodityCode: $data['commodity_code'],
                netMassTons: (float) $data['net_mass_tons'],
                embeddedEmissionsTco2: (float) $data['embedded_emissions_tco2'],
                eurToIdrRate: isset($data['eur_to_idr_rate']) ? (int) $data['eur_to_idr_rate'] : 17_000,
                cbamPriceEurPerTon: isset($data['cbam_price_eur_per_ton']) ? (int) $data['cbam_price_eur_per_ton'] : 80,
                carbonCreditRef: $data['carbon_credit_ref'] ?? null
            );

            return back()->with('success', "Sertifikat CBAM {$cert->cert_code} diterbitkan. Kewajiban bea emisi: Rp ".number_format($cert->cbam_levy_cost_idr));
        } catch (Throwable $e) {
            return back()->withErrors(['cbam' => $e->getMessage()]);
        }
    }
}
