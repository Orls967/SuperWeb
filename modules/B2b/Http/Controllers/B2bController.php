<?php

namespace Modules\B2b\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\B2b\Application\Services\B2bService;
use Modules\B2b\Domain\Models\B2bEscrowAccount;
use Modules\B2b\Domain\Models\B2bRfq;
use Modules\B2b\Domain\Models\SurplusAuction;
use Modules\B2b\Domain\Models\WholesaleCatalog;

class B2bController extends Controller
{
    public function index(Request $request, B2bService $service)
    {
        $catalogs = WholesaleCatalog::latest()->take(20)->get();
        $rfqs = B2bRfq::with('catalog')->latest()->take(20)->get();
        $auctions = SurplusAuction::latest()->take(20)->get();
        $escrows = B2bEscrowAccount::latest()->take(20)->get();
        $audit = $service->auditB2b();

        return view('b2b::b2b.index', compact('catalogs', 'rfqs', 'auctions', 'escrows', 'audit'));
    }
}
