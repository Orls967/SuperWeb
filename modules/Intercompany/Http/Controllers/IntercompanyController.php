<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;
use Modules\Intercompany\Domain\Models\TransferPricingRule;

class IntercompanyController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = IntercompanyTransaction::orderByDesc('created_at')->limit(10)->get();
        $loans = IntercompanyLoan::orderByDesc('created_at')->limit(10)->get();
        $rules = TransferPricingRule::orderByDesc('created_at')->limit(10)->get();
        $eliminations = EliminationEntry::orderByDesc('created_at')->limit(10)->get();
        $ncis = SubsidiaryNci::orderByDesc('created_at')->limit(10)->get();

        return view('intercompany::index', compact('transactions', 'loans', 'rules', 'eliminations', 'ncis'));
    }
}
