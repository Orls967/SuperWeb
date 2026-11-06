<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Actions\RetireRecCertificateAction;
use Modules\Egy\Application\Services\CarbonTradingAndEsgMarketplaceService;
use Modules\Egy\Domain\Models\RecCertificate;

final class EgyRecController extends Controller
{
    public function __construct(
        private readonly CarbonTradingAndEsgMarketplaceService $marketplaceService,
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->marketplaceService->getRecInventoryOverview();

        return view('egy::rec.index', $overview);
    }

    public function retire(Request $request, RetireRecCertificateAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'certificate_id' => 'required|integer|exists:egy_rec_certificates,id',
            'beneficiary_name' => 'required|string|max:120',
            'retirement_reason' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $partyId = (int) ($user->party_id ?? $user->id ?? 1);

        $certificate = $action->execute(
            recCertificateId: (int) $validated['certificate_id'],
            beneficiaryName: $validated['beneficiary_name'],
            retirementReason: $validated['retirement_reason'],
            claimantPartyId: $partyId
        );

        return redirect()->route('egy.rec.index')->with(
            'success',
            "Sertifikat {$certificate->certificate_serial} berhasil dipensiunkan untuk {$certificate->retirement_beneficiary}."
        );
    }

    public function export(): Response
    {
        $certificates = RecCertificate::all();
        $headers = [
            'Certificate Serial',
            'Generator Asset ID',
            'Source Type',
            'Period Month',
            'Vintage Year',
            'Energy (Wh)',
            'Owner Party ID',
            'Status',
            'Retired At',
            'Beneficiary',
            'SHA-256 Hash',
        ];

        $rows = array_map(function ($c) {
            return [
                $c->certificate_serial,
                $c->generation_asset_id,
                $c->energy_source_type,
                $c->period_month,
                $c->vintage_year,
                $c->energy_wh,
                $c->owner_party_id,
                $c->status,
                $c->retired_at ? $c->retired_at->toDateTimeString() : '',
                $c->retirement_beneficiary ?? '',
                $c->certificate_hash,
            ];
        }, iterator_to_array($certificates));

        $csv = $this->marketplaceService->formatAsCsv($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rec-registry-audit.csv"',
        ]);
    }
}
