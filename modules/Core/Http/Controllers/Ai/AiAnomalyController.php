<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Services\Ai\TransactionAnomalyDetectorService;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;

class AiAnomalyController extends Controller
{
    public function index(): View
    {
        $quarantines = CoreAiAnomalyQuarantine::orderBy('created_at', 'desc')->take(20)->get();

        return view('core::ai.anomalies', [
            'quarantines' => $quarantines,
        ]);
    }

    public function screen(Request $request, TransactionAnomalyDetectorService $detector): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_ref' => ['required', 'string', 'max:64'],
            'customer_ref' => ['required', 'string', 'max:64'],
            'amount_idr' => ['required', 'integer', 'min:1'],
            'channel' => ['nullable', 'string', 'max:32'],
            'recent_tx_count_10m' => ['nullable', 'integer', 'min:0'],
            'historical_amounts' => ['nullable', 'array'],
        ]);

        $res = $detector->evaluateTransaction(
            transactionRef: (string) $validated['transaction_ref'],
            customerRef: (string) $validated['customer_ref'],
            amountIdr: (int) $validated['amount_idr'],
            channel: (string) ($validated['channel'] ?? 'ONLINE'),
            recentTxCount10m: (int) ($validated['recent_tx_count_10m'] ?? 1),
            historicalAmounts: (array) ($validated['historical_amounts'] ?? []),
        );

        $msg = $res->isQuarantined
            ? "Transaksi {$res->transactionRef} berhasil dievaluasi dan masuk status QUARANTINED."
            : "Transaksi {$res->transactionRef} normal (tidak dikarantina).";

        return redirect()->route('core.ai.anomalies.index')->with('success', $msg);
    }
}

