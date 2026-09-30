<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\OutletContract;
use Modules\Resto\Domain\Models\RoyaltyPosting;

class FranchiseController extends Controller
{
    public function index(): View
    {
        $contracts = OutletContract::with(['outlet', 'franchisee'])
            ->latest('id')
            ->get();

        $postings = RoyaltyPosting::with(['outlet', 'contract'])
            ->latest('date')
            ->limit(20)
            ->get();

        $outlets = Outlet::where('is_active', true)->get();
        $users = User::all();

        return view('resto::franchise.index', [
            'contracts' => $contracts,
            'postings' => $postings,
            'outlets' => $outlets,
            'users' => $users,
        ]);
    }

    public function storeContract(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'exists:resto_outlets,id', 'unique:resto_outlet_contracts,outlet_id'],
            'franchisee_user_id' => ['nullable', 'exists:users,id'],
            'royalty_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'marketing_fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
        ]);

        $contractNumber = 'CTR-FRN-'.strtoupper(Str::random(6));

        OutletContract::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $validated['outlet_id'],
            'franchisee_user_id' => $validated['franchisee_user_id'] ?? null,
            'contract_number' => $contractNumber,
            'royalty_percent' => (string) $validated['royalty_percent'],
            'marketing_fee_percent' => (string) $validated['marketing_fee_percent'],
            'fixed_monthly_management_fee' => 0,
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
            'is_active' => true,
        ]);

        return back()->with('success', "Kontrak franchise {$contractNumber} berhasil dibuat.");
    }

    public function runRoyalty(Request $request): RedirectResponse
    {
        $date = $request->input('date') ?: now()->toDateString();
        Artisan::call('resto:post-royalty', ['--date' => $date]);

        $output = trim(Artisan::output());

        return back()->with('success', "Proses royalti franchise berhasil dijalankan: {$output}");
    }
}
