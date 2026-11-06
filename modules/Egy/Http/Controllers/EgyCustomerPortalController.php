<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;

class EgyCustomerPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $partyId = (string) ($user?->id ?? '');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');

        $metersQuery = SmartMeter::query();
        if (! $isAdmin) {
            $metersQuery->where(function ($q) use ($partyId) {
                $q->where('customer_party_id', $partyId)
                  ->orWhere('consumer_property_id', $partyId);
            });
        }
        $meters = $metersQuery->get();

        $invoicesQuery = UtilityConsolidatedInvoice::query();
        if (! $isAdmin) {
            $invoicesQuery->where('customer_party_id', $partyId);
        }
        $invoices = $invoicesQuery->orderBy('created_at', 'desc')->paginate(10);

        return view('egy::customer.index', [
            'customerId' => $user?->name ?? ('User #' . $partyId),
            'propertyId' => $meters->first()?->property_id ?? 'PROP-DEFAULT',
            'meters' => $meters,
            'invoices' => $invoices,
            'outstandingTotal' => (int) $invoices->where('status', 'issued')->sum('total_amount_idr'),
            'totalUnpaidIdr' => (int) $invoices->where('status', 'issued')->sum('total_amount_idr'),
        ]);
    }

    public function showBill(Request $request, string $id): View
    {
        $user = $request->user();
        $invoice = UtilityConsolidatedInvoice::with('lines')->findOrFail($id);

        // Anti-IDOR check
        $isAdminOrBilling = $user && method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('energy_billing'));
        if (! $isAdminOrBilling) {
            $userPartyId = (string) ($user?->id ?? '');
            if ((string) $invoice->customer_party_id !== $userPartyId) {
                abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk melihat tagihan ini.');
            }
        }

        return view('egy::customer.bill', [
            'invoice' => $invoice,
        ]);
    }
}
