<?php

declare(strict_types=1);

namespace Modules\AutoServe\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Banking\Application\Actions\VerifyPinAction;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Exceptions\PinLockedException;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Shared\Domain\ValueObjects\Money;

class InvoicePaymentController extends Controller
{
    public function pay(
        Request $request,
        Booking $booking,
        PaymentGateway $paymentGateway,
        VerifyPinAction $verifyPin,
    ): RedirectResponse {
        $user = $request->user();

        // Authorization check: customer or admin
        if ($booking->customer_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'Anda tidak memiliki hak akses untuk membayar tagihan ini.');
        }

        if ($booking->isPaid()) {
            return back()->with('error', 'Tagihan invoice ini sudah berstatus lunas.');
        }

        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ], [
            'pin.required' => 'PIN transaksi 6-digit wajib diisi.',
            'pin.digits' => 'PIN harus terdiri dari tepat 6 digit.',
        ]);

        try {
            // Verify customer PIN
            $verifyPin->execute($user, (string) $validated['pin']);

            // Process payment via PaymentGateway
            $idempotencyKey = "invoice_pay_{$booking->id}_{$booking->booking_code}";
            $intent = $paymentGateway->charge($booking, $idempotencyKey);

            return back()->with('success', "Pembayaran invoice {$booking->booking_code} sebesar {$booking->payableAmount()->format()} berhasil. Status: LUNAS.");
        } catch (InvalidPinException $e) {
            return back()->with('error', $e->getMessage());
        } catch (PinLockedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (InsufficientFundsException $e) {
            return back()->with('error', 'Saldo dompet tidak mencukupi untuk membayar tagihan ini.');
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal memproses pembayaran: {$e->getMessage()}");
        }
    }

    public function refund(
        Request $request,
        Booking $booking,
        PaymentGateway $paymentGateway,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user->isAdmin()) {
            abort(403, 'Hanya Administrator yang dapat melakukan refund pembayaran.');
        }

        if (! $booking->isPaid()) {
            return back()->with('error', 'Hanya invoice berstatus lunas yang dapat di-refund.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ], [
            'reason.required' => 'Alasan refund wajib diisi.',
        ]);

        $intent = $booking->latestPaymentIntent;
        if (! $intent) {
            return back()->with('error', 'Data payment intent tidak ditemukan untuk invoice ini.');
        }

        try {
            $refundMoney = ! empty($validated['amount'])
                ? Money::IDR($validated['amount'])
                : null;

            $paymentGateway->refund($intent, $refundMoney, $validated['reason']);

            return back()->with('success', "Refund untuk invoice {$booking->booking_code} berhasil diproses.");
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal memproses refund: {$e->getMessage()}");
        }
    }
}
