<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\ForwardContract;

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
}
