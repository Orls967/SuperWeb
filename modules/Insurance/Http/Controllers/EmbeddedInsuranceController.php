<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Actions\IssueEmbeddedPolicyAction;
use Modules\Insurance\Domain\Models\EmbedPolicy;
use Modules\Insurance\Domain\Models\EmbedProduct;
use Modules\Insurance\Domain\Models\MarketBroker;

final class EmbeddedInsuranceController extends Controller
{
    public function matrix(Request $request)
    {
        $domain = $request->query('domain');
        $query = EmbedProduct::query()->where('is_active', true);
        if ($domain !== null && $domain !== '') {
            $query->where('line_domain', strtoupper((string) $domain));
        }
        $products = $query->orderBy('line_domain')->get();
        $brokers = MarketBroker::query()->where('status', 'active')->with('party')->get();

        return view('insurance::embedded.matrix', compact('products', 'brokers', 'domain'));
    }

    public function policies(Request $request)
    {
        $policies = EmbedPolicy::with(['product', 'broker.party', 'customer'])
            ->orderByDesc('id')
            ->paginate(15);

        return view('insurance::embedded.policies', compact('policies'));
    }

    public function issue(Request $request, IssueEmbeddedPolicyAction $action)
    {
        $validated = $request->validate([
            'event_key' => 'required|string|max:120',
            'product_code' => 'required|string|exists:ins_embed_products,code',
            'customer_party_id' => 'required|string',
            'context_type' => 'required|string|max:40',
            'context_ref' => 'required|string|max:80',
            'custom_sum_insured_idr' => 'nullable|integer|min:1000',
            'broker_party_id' => 'nullable|string',
        ]);

        $policy = $action->execute(
            eventKey: $validated['event_key'],
            productCode: $validated['product_code'],
            customerPartyId: $validated['customer_party_id'],
            contextType: $validated['context_type'],
            contextRef: $validated['context_ref'],
            customSumInsuredIdr: isset($validated['custom_sum_insured_idr']) ? (int) $validated['custom_sum_insured_idr'] : null,
            brokerPartyId: $validated['broker_party_id'] ?? null,
        );

        return redirect()->route('insurance.embedded.policies')
            ->with('success', "Polis embedded {$policy->policy_number} berhasil diterbitkan.");
    }
}
