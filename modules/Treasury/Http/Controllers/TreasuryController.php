<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankStatement;
use Modules\Treasury\Domain\Models\CashForecast;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\Revaluation;

class TreasuryController extends Controller
{
    public function __construct(
        protected TreasuryService $treasuryService
    ) {}

    public function index(Request $request): View
    {
        $currencies = Currency::orderBy('code')->get();
        $rates = ExchangeRate::orderByDesc('rate_date')->limit(15)->get();
        $accounts = BankAccount::orderBy('bank_name')->get();
        $facilities = CreditFacility::orderBy('facility_code')->get();
        $forwards = ForwardContract::orderByDesc('created_at')->limit(10)->get();

        return view('treasury::index', compact(
            'currencies',
            'rates',
            'accounts',
            'facilities',
            'forwards'
        ));
    }

    public function positions(Request $request): View
    {
        $accounts = BankAccount::orderBy('bank_name')->get();
        $totalIdr = (int) BankAccount::where('currency', 'IDR')->sum('balance');
        $totalUsd = (int) BankAccount::where('currency', 'USD')->sum('balance');

        return view('treasury::positions', compact('accounts', 'totalIdr', 'totalUsd'));
    }

    public function fx(Request $request): View
    {
        $currencies = Currency::where('is_active', true)->get();
        $rates = ExchangeRate::orderByDesc('rate_date')->limit(30)->get();
        $lots = FxLot::where('status', 'active')->orderByDesc('lot_date')->get();
        $revaluations = Revaluation::orderByDesc('period')->limit(20)->get();

        return view('treasury::fx', compact('currencies', 'rates', 'lots', 'revaluations'));
    }

    public function reconciliation(Request $request): View
    {
        $accounts = BankAccount::orderBy('bank_name')->get();
        $statements = BankStatement::orderByDesc('transaction_date')->limit(50)->get();
        $unreconciledCount = BankStatement::where('is_reconciled', false)->count();

        return view('treasury::reconciliation', compact('accounts', 'statements', 'unreconciledCount'));
    }

    public function forecast(Request $request): View
    {
        $forecasts = CashForecast::orderBy('week_number')->get();

        return view('treasury::forecast', compact('forecasts'));
    }

    public function hedges(Request $request): View
    {
        $contracts = ForwardContract::with(['histories', 'currencyRef'])->orderByDesc('created_at')->get();

        return view('treasury::hedges', compact('contracts'));
    }

    public function facilities(Request $request): View
    {
        $facilities = CreditFacility::with('drawdowns')->orderBy('facility_code')->get();

        return view('treasury::facilities', compact('facilities'));
    }

    public function pools(Request $request): View
    {
        $pools = CashPool::with(['headerAccount', 'subAccount', 'sweeps'])->get();

        return view('treasury::pools', compact('pools'));
    }
}
