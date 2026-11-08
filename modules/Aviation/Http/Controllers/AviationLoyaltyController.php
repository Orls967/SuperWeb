<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Application\Services\AviationLoyaltyProgramService;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;
use Modules\Aviation\Http\ViewModels\LoyaltyProgramViewModel;

class AviationLoyaltyController extends Controller
{
    public function __construct(
        protected AviationLoyaltyProgramService $loyaltyService
    ) {}

    public function index(Request $request): View
    {
        $query = AviationLoyaltyAccount::with(['airline', 'transactions'])
            ->latest('created_at');

        if ($request->filled('tier')) {
            $query->where('current_tier', $request->string('tier'));
        }

        $accounts = $query->paginate(15);

        $metrics = [
            'total_members' => AviationLoyaltyAccount::count(),
            'elit_members' => AviationLoyaltyAccount::whereIn('current_tier', ['silver', 'gold', 'platinum'])->count(),
            'total_miles' => (int) AviationLoyaltyAccount::sum('redeemable_miles'),
        ];

        $vm = new LoyaltyProgramViewModel($accounts, $metrics);

        return view('aviation::loyalty.index', ['vm' => $vm]);
    }

    public function enroll(Request $request): RedirectResponse
    {
        $request->validate([
            'airline_id' => ['required', 'integer', 'exists:avi_airlines,id'],
            'passenger_party_ref' => ['required', 'string'],
            'initial_tier' => ['nullable', 'string', 'in:classic,silver,gold,platinum'],
        ]);

        $account = $this->loyaltyService->enrollMember(
            $request->integer('airline_id'),
            $request->input('passenger_party_ref'),
            $request->input('initial_tier', 'classic')
        );

        return redirect()->route('aviation.loyalty.index')
            ->with('status', sprintf('Anggota frequent flyer baru [%s] berhasil didaftarkan.', $account->membership_number));
    }
}
