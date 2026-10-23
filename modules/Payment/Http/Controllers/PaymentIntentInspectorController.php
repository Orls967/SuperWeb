<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Payment\Domain\Models\PaymentIntent;

class PaymentIntentInspectorController extends Controller
{
    public function index(Request $request): View
    {
        $query = PaymentIntent::query()->with('payer');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('currency')) {
            $query->where('currency', strtoupper((string) $request->query('currency')));
        }

        $totalIntents = (clone $query)->count();
        $capturedCount = (clone $query)->where('status', PaymentIntentStatus::CAPTURED)->count();
        $heldCount = (clone $query)->where('status', PaymentIntentStatus::HELD)->count();

        $intents = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('payment::inspector.index', [
            'intents' => $intents,
            'totalIntents' => $totalIntents,
            'capturedCount' => $capturedCount,
            'heldCount' => $heldCount,
            'statuses' => PaymentIntentStatus::cases(),
            'currentStatus' => $request->query('status'),
            'currentCurrency' => $request->query('currency'),
        ]);
    }

    public function show(string $uuid): View
    {
        $intent = PaymentIntent::where('uuid', $uuid)
            ->with('payer')
            ->firstOrFail();

        return view('payment::inspector.show', [
            'intent' => $intent,
        ]);
    }
}
