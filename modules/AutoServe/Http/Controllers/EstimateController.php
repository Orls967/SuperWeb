<?php

declare(strict_types=1);

namespace Modules\AutoServe\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\AutoServe\Application\Actions\ApproveEstimateAction;
use Modules\AutoServe\Application\Actions\ApproveExtraChargeAction;
use Modules\AutoServe\Application\Actions\CreateEstimateAction;
use Modules\AutoServe\Application\Actions\ReceiveBackorderAction;
use Modules\AutoServe\Application\Actions\RejectEstimateAction;
use Modules\AutoServe\Application\Actions\SendEstimateAction;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\AutoServe\Http\Requests\StoreEstimateRequest;

class EstimateController extends Controller
{
    public function __construct(
        private readonly CreateEstimateAction $createEstimate,
        private readonly SendEstimateAction $sendEstimate,
        private readonly ApproveEstimateAction $approveEstimate,
        private readonly RejectEstimateAction $rejectEstimate,
        private readonly ApproveExtraChargeAction $approveExtraCharge,
        private readonly ReceiveBackorderAction $receiveBackorder,
    ) {}

    public function store(StoreEstimateRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $estimate = $this->createEstimate->execute($booking, $request->user(), $request->input('items', []));

            if ($request->boolean('send_now')) {
                $this->sendEstimate->execute($estimate);

                return back()->with('success', 'Estimasi tersimpan dan sudah dikirim ke customer untuk disetujui.');
            }

            return back()->with('success', 'Draf estimasi tersimpan.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function send(Estimate $estimate): RedirectResponse
    {
        try {
            $this->sendEstimate->execute($estimate);

            return back()->with('success', 'Estimasi dikirim ke customer.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, Estimate $estimate): RedirectResponse
    {
        $request->validate([
            'pin' => 'required|string|size:6',
        ]);

        try {
            $estimate = $this->approveEstimate->execute($estimate, $request->user(), (string) $request->input('pin'));

            $message = $estimate->backorder_order_id
                ? 'Estimasi disetujui. Dana ditahan di escrow; sebagian sparepart dipesan dulu (menunggu sparepart).'
                : 'Estimasi disetujui. Dana ditahan di escrow dan pengerjaan dimulai.';

            return back()->with('success', $message);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, Estimate $estimate): RedirectResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $this->rejectEstimate->execute($estimate, $request->user(), (string) $request->input('reason', ''));

            return back()->with('success', 'Estimasi ditolak. Mekanik dapat menyusun estimasi baru.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approveExtra(Request $request, Booking $booking): RedirectResponse
    {
        $request->validate([
            'pin' => 'required|string|size:6',
        ]);

        try {
            $this->approveExtraCharge->execute($booking, $request->user(), (string) $request->input('pin'));

            return back()->with('success', 'Biaya tambahan dibayar. Servis diselesaikan dan dana escrow dicairkan.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receiveBackorder(Estimate $estimate): RedirectResponse
    {
        try {
            $this->receiveBackorder->execute($estimate);

            return back()->with('success', 'Sparepart backorder diterima. Stok masuk dan pengerjaan dilanjutkan.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
