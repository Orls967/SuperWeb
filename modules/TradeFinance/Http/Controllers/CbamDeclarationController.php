<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\TradeFinance\Application\Services\Clearing\CbamDeclarationService;
use Modules\TradeFinance\Http\Requests\DeclareCbamRequest;
use Throwable;

class CbamDeclarationController extends Controller
{
    public function __construct(private readonly CbamDeclarationService $service) {}

    public function index(): View
    {
        return view('trade_finance::cbam', [
            'declarations' => $this->service->recent(),
            'summary' => $this->service->summary(),
        ]);
    }

    public function declare(DeclareCbamRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $cert = $this->service->declare(
                containerNumber: $data['container_number'],
                productCode: $data['product_code'],
                originCountry: $data['origin_country'],
                netMassKg: (int) $data['net_mass_kg'],
                idempotencyKey: $data['idempotency_key'],
                actor: $request->user(),
            );

            return back()->with('success', "Sertifikat CBAM {$cert->certificate_number} terbit: {$cert->embeddedTco2e()} tCO2e.");
        } catch (Throwable $e) {
            return back()->withErrors(['cbam' => $e->getMessage()])->withInput();
        }
    }
}
