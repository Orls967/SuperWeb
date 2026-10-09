<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\TradeFinance\Domain\Models\BankGuarantee;
use Modules\TradeFinance\Domain\Models\DocumentaryCollection;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;
use Modules\TradeFinance\Domain\Models\TradeLoan;

class TradeFinanceController extends Controller
{
    public function index(Request $request): View
    {
        $lcs = LetterOfCredit::with('documents')->orderByDesc('created_at')->limit(10)->get();
        $collections = DocumentaryCollection::orderByDesc('created_at')->limit(10)->get();
        $guarantees = BankGuarantee::orderByDesc('created_at')->limit(10)->get();
        $loans = TradeLoan::orderByDesc('created_at')->limit(10)->get();

        return view('trade_finance::index', compact('lcs', 'collections', 'guarantees', 'loans'));
    }
}
