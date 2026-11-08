<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Application\Services\AviationCodeshareService;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;
use Modules\Aviation\Domain\Models\AviationCodeshareFlight;
use Modules\Aviation\Http\Requests\CreateCodeshareAgreementRequest;
use Modules\Aviation\Http\ViewModels\CodeshareAgreementViewModel;

class AviationCodeshareController extends Controller
{
    public function __construct(
        protected AviationCodeshareService $codeshareService
    ) {}

    public function index(Request $request): View
    {
        $query = AviationCodeshareAgreement::with(['operatingAirline', 'marketingAirline', 'codeshareFlights'])
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $agreements = $query->paginate(15);

        $metrics = [
            'active' => AviationCodeshareAgreement::where('status', 'active')->count(),
            'total_routes' => AviationCodeshareFlight::where('status', 'active')->count(),
        ];

        $vm = new CodeshareAgreementViewModel($agreements, $metrics);

        return view('aviation::codeshare.index', ['vm' => $vm]);
    }

    public function store(CreateCodeshareAgreementRequest $request): RedirectResponse
    {
        $agreement = $this->codeshareService->createAgreement($request->validated());

        return redirect()->route('aviation.codeshare.index')
            ->with('status', sprintf('Perjanjian codeshare [%s] berhasil didaftarkan.', $agreement->agreement_code));
    }

    public function activate(int $id): RedirectResponse
    {
        $agreement = $this->codeshareService->activateAgreement($id);

        return redirect()->route('aviation.codeshare.index')
            ->with('status', sprintf('Perjanjian codeshare [%s] kini berstatus AKTIF.', $agreement->agreement_code));
    }
}
