<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Partner\Application\Services\PartnerService;
use Modules\Partner\Domain\Models\Partner;

class PartnerController extends Controller
{
    public function __construct(private readonly PartnerService $service) {}

    public function index(): View
    {
        return view('partner::index', [
            'partners' => Partner::with(['dueDiligences', 'jointPlans', 'revenueShares'])->orderBy('code')->get(),
        ]);
    }

    public function show(Partner $partner): View
    {
        $partner->load(['dueDiligences', 'jointPlans', 'revenueShares', 'cosellListings', 'scorecards', 'intellectualProperties', 'exitTransitions']);

        return view('partner::show', [
            'partner' => $partner,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:180',
            'kind' => 'required|string|max:32',
            'notes' => 'nullable|string',
        ]);

        $this->service->registerPartner($data);

        return back()->with('success', 'Mitra berhasil didaftarkan.');
    }

    public function transition(Partner $partner, Request $request): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|string']);
        $this->service->transition($partner, $data['status']);

        return back()->with('success', 'Status kemitraan diperbarui.');
    }
}
