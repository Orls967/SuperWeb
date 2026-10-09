<?php

declare(strict_types=1);

namespace Modules\Trade\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Trade\Domain\Models\Country;
use Modules\Trade\Domain\Models\ExportOrder;
use Modules\Trade\Domain\Models\HsCode;
use Modules\Trade\Domain\Models\ImportOrder;
use Modules\Trade\Domain\Models\Incoterm;
use Modules\Trade\Domain\Models\TradeDispute;

class TradeController extends Controller
{
    public function index(Request $request): View
    {
        $countries = Country::with('ports')->orderBy('name')->get();
        $incoterms = Incoterm::orderBy('code')->get();
        $hsCodes = HsCode::orderBy('hs_code')->limit(20)->get();
        $exportOrders = ExportOrder::with(['destinationCountry', 'destinationPort', 'incoterm'])->orderByDesc('created_at')->limit(10)->get();
        $importOrders = ImportOrder::with(['originCountry', 'originPort', 'incoterm'])->orderByDesc('created_at')->limit(10)->get();
        $disputes = TradeDispute::orderByDesc('created_at')->limit(5)->get();

        return view('trade::index', compact(
            'countries',
            'incoterms',
            'hsCodes',
            'exportOrders',
            'importOrders',
            'disputes'
        ));
    }
}
