<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Harga, Promo &amp; Trade Terms</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">44.1–44.7 — price list, waterfall diskon, promo+klaim, price lock, margin floor, analitik.</div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">44.1 Price list</h3>
                <form method="POST" action="{{ route('pricing.price-lists.store') }}" class="mt-3 grid gap-2 md:grid-cols-5 text-sm">
                    @csrf
                    <input name="code" placeholder="Kode" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                    <select name="channel" class="rounded border-gray-300">@foreach ($channels as $channel)<option value="{{ $channel }}">{{ $channel }}</option>@endforeach</select>
                    <input name="segment" placeholder="Segmen (opsional)" class="rounded border-gray-300"><input name="region_code" placeholder="Wilayah (opsional)" class="rounded border-gray-300">
                    <input name="currency" value="IDR" required class="rounded border-gray-300"><input name="priority" type="number" min="1" value="100" required class="rounded border-gray-300">
                    <input name="valid_from" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300"><input name="valid_until" type="date" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Buat draft</button>
                </form>
                <form method="POST" action="{{ route('pricing.price-lists.items.store') }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <select name="price_list_id" required class="rounded border-gray-300"><option value="">Draft</option>@foreach ($priceLists as $list)<option value="{{ $list->id }}">{{ $list->code }} ({{ $list->items_count }} item)</option>@endforeach</select>
                    <input name="sku" placeholder="SKU" required class="rounded border-gray-300"><input name="price_idr" type="number" min="0" required placeholder="Harga" class="w-32 rounded border-gray-300"><input name="min_qty" type="number" step="0.01" min="0.01" value="1" required class="w-20 rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Tambah item</button>
                </form>
                <div class="mt-3 space-y-2">
                    @foreach ($priceLists as $list)
                        <div class="flex items-center justify-between rounded border p-3 text-sm">
                            <span>{{ $list->code }} — {{ $list->name }} · {{ $list->channel }}{{ $list->segment ? ' · '.$list->segment : '' }}{{ $list->region_code ? ' · '.$list->region_code : '' }} · {{ $list->status }} · {{ $list->items_count }} item</span>
                            @if ($list->status === 'draft')<form method="POST" action="{{ route('pricing.price-lists.activate', $list) }}">@csrf<button class="text-emerald-700 underline">Aktifkan</button></form>@endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">44.2 Aturan diskon (waterfall deterministik)</h3>
                <form method="POST" action="{{ route('pricing.discounts.store') }}" class="mt-3 grid gap-2 md:grid-cols-5 text-sm">
                    @csrf
                    <input name="code" placeholder="Kode" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                    <select name="kind" class="rounded border-gray-300"><option value="volume">Volume</option><option value="bundle">Bundle</option><option value="combo">Kombinasi</option><option value="coupon">Kupon</option></select>
                    <select name="channel" class="rounded border-gray-300">@foreach ($channels as $channel)<option value="{{ $channel }}">{{ $channel }}</option>@endforeach</select>
                    <input name="threshold_qty" type="number" step="0.01" min="0" placeholder="Ambang qty" class="rounded border-gray-300">
                    <input name="threshold_amount_idr" type="number" min="0" placeholder="Ambang nilai" class="rounded border-gray-300">
                    <input name="percent_off" type="number" step="0.01" min="0" max="100" placeholder="Diskon %" class="rounded border-gray-300">
                    <input name="amount_off_idr" type="number" min="0" placeholder="Diskon nominal" class="rounded border-gray-300">
                    <input name="order" type="number" min="1" value="10" required class="w-20 rounded border-gray-300">
                    <input name="coupon_code" placeholder="Kode kupon" class="rounded border-gray-300">
                    <input name="valid_from" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300">
                    <input name="valid_until" type="date" class="rounded border-gray-300">
                    <label class="flex items-center gap-1"><input type="checkbox" name="stackable" value="1" checked> Dapat ditumpuk</label>
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Simpan</button>
                </form>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Urutan</th><th class="p-2 text-left">Kode</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-right">%</th><th class="p-2 text-right">Nominal</th><th class="p-2 text-left">Stackable</th></tr></thead><tbody>
                    @foreach ($discountRules as $rule)<tr class="border-t"><td class="p-2">{{ $rule->order }}</td><td class="p-2">{{ $rule->code }}</td><td class="p-2">{{ $rule->kind }}</td><td class="p-2 text-right">{{ $rule->percent_off }}</td><td class="p-2 text-right">{{ number_format($rule->amount_off_idr) }}</td><td class="p-2">{{ $rule->stackable ? 'ya' : 'tidak' }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between"><h3 class="text-lg font-semibold">44.3 Promo dagang &amp; klaim</h3>
                    <form method="POST" action="{{ route('pricing.analytics.compute') }}" class="flex gap-2 text-sm">@csrf<input name="period" placeholder="YYYY-MM" value="{{ now()->format('Y-m') }}" required class="rounded border-gray-300"><select name="channel" class="rounded border-gray-300">@foreach ($channels as $channel)<option value="{{ $channel }}">{{ $channel }}</option>@endforeach</select><button class="rounded bg-amber-600 px-3 py-2 text-white">44.7 Analitik</button></form>
                </div>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('pricing.promotions.store') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <input name="code" placeholder="Kode" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                        <select name="mechanic" class="rounded border-gray-300"><option value="off_invoice">Off-invoice</option><option value="bill_back">Bill-back</option><option value="scan_back">Scan-back</option></select>
                        <input name="budget_idr" type="number" min="0" required placeholder="Anggaran" class="w-32 rounded border-gray-300">
                        <input name="percent_off" type="number" step="0.01" min="0" max="100" placeholder="%" class="w-20 rounded border-gray-300">
                        <input name="valid_from" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300"><input name="valid_until" type="date" required class="rounded border-gray-300">
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Buat promo</button>
                    </form>
                    <form method="POST" action="{{ route('pricing.promotions.claims.store') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <select name="promotion_id" required class="rounded border-gray-300"><option value="">Promo</option>@foreach ($promotions as $promotion)<option value="{{ $promotion->id }}">{{ $promotion->code }}</option>@endforeach</select>
                        <input name="source_ref" placeholder="Ref order/faktur" required class="rounded border-gray-300"><input name="amount_idr" type="number" min="1" required placeholder="Nilai klaim" class="w-32 rounded border-gray-300">
                        <input name="evidence_note" placeholder="Bukti (wajib)" required class="rounded border-gray-300">
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Kirim klaim</button>
                    </form>
                </div>
                <div class="mt-3 space-y-2">
                    @foreach ($promotions as $promotion)
                        <div class="rounded border p-3 text-sm">{{ $promotion->code }} — {{ $promotion->name }} · {{ $promotion->mechanic }} · anggaran {{ number_format($promotion->budget_idr) }} / terpakai {{ number_format($promotion->spent_idr) }} · sisa {{ number_format($promotion->remainingBudget()) }}</div>
                    @endforeach
                    @foreach ($claims as $claim)
                        <div class="flex items-center justify-between rounded border p-3 text-sm">
                            <span>{{ $claim->promotion?->code }} — {{ $claim->source_ref }} · {{ number_format($claim->amount_idr) }} · {{ $claim->status }} · bukti: {{ \Illuminate\Support\Str::limit($claim->evidence_note, 40) }}</span>
                            <span class="flex gap-2">
                                @if ($claim->status === 'submitted')<form method="POST" action="{{ route('pricing.claims.validate', $claim) }}">@csrf<button class="text-amber-700 underline">Validasi</button></form>@endif
                                @if ($claim->status === 'validated')<form method="POST" action="{{ route('pricing.claims.settle', $claim) }}">@csrf<button class="text-emerald-700 underline">Settle</button></form>@endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">44.5 Margin floor &amp; override harga</h3>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('pricing.margin-policies.store') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <select name="channel" class="rounded border-gray-300">@foreach ($channels as $channel)<option value="{{ $channel }}">{{ $channel }}</option>@endforeach</select>
                        <input name="sku" placeholder="SKU (opsional)" class="rounded border-gray-300"><input name="min_margin_percent" type="number" step="0.01" min="0" max="100" required placeholder="Margin min %" class="w-28 rounded border-gray-300"><input name="floor_cost_idr" type="number" min="0" required placeholder="Floor cost" class="w-32 rounded border-gray-300">
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Simpan kebijakan</button>
                    </form>
                    <form method="POST" action="{{ route('pricing.overrides.store') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <input name="sku" placeholder="SKU" required class="rounded border-gray-300"><select name="channel" class="rounded border-gray-300">@foreach ($channels as $channel)<option value="{{ $channel }}">{{ $channel }}</option>@endforeach</select>
                        <input name="proposed_price_idr" type="number" min="0" required placeholder="Harga usulan" class="w-32 rounded border-gray-300"><input name="reason" placeholder="Alasan (wajib)" required class="rounded border-gray-300">
                        <button class="rounded bg-rose-600 px-3 py-2 text-white">Ajukan override</button>
                    </form>
                </div>
                <div class="mt-3 space-y-2">
                    @foreach ($overrides as $override)
                        <div class="flex items-center justify-between rounded border p-3 text-sm">
                            <span>{{ $override->sku }} · {{ $override->channel }} · {{ number_format($override->proposed_price_idr) }} (floor {{ number_format($override->floor_price_idr) }}, margin {{ number_format((float) $override->margin_percent, 1) }}%) · {{ $override->status }} — {{ \Illuminate\Support\Str::limit($override->reason, 50) }}</span>
                            @if ($override->status === 'pending')<span class="flex gap-2"><form method="POST" action="{{ route('pricing.overrides.decide', $override) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="text-emerald-700 underline">Setujui</button></form><form method="POST" action="{{ route('pricing.overrides.decide', $override) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="text-rose-700 underline">Tolak</button></form></span>@endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">44.4 Price lock &amp; 44.7 Analitik</h3>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Dokumen</th><th class="p-2 text-left">SKU</th><th class="p-2 text-right">Qty</th><th class="p-2 text-right">List</th><th class="p-2 text-right">Diterapkan</th><th class="p-2 text-right">Diskon</th><th class="p-2 text-left">Sumber</th></tr></thead><tbody>
                    @foreach ($locks as $lock)
                        <tr class="border-t"><td class="p-2">{{ $lock->subject_type }}#{{ substr($lock->subject_id, 0, 8) }}</td><td class="p-2">{{ $lock->sku }}</td><td class="p-2 text-right">{{ $lock->qty }}</td><td class="p-2 text-right">{{ number_format($lock->list_price_idr) }}</td><td class="p-2 text-right">{{ number_format($lock->applied_price_idr) }}</td><td class="p-2 text-right">{{ number_format($lock->discount_idr) }}</td><td class="p-2">{{ $lock->source_kind }}</td></tr>
                    @endforeach
                </tbody></table></div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Periode</th><th class="p-2 text-left">Channel</th><th class="p-2 text-right">Realisasi vs list %</th><th class="p-2 text-right">Leakage</th><th class="p-2 text-right">Diskon rata² %</th><th class="p-2 text-right">Efektivitas promo %</th></tr></thead><tbody>
                    @foreach ($analytics as $snapshot)
                        <tr class="border-t"><td class="p-2">{{ $snapshot->period }}</td><td class="p-2">{{ $snapshot->channel }}</td><td class="p-2 text-right">{{ number_format((float) $snapshot->realized_vs_list_percent, 1) }}</td><td class="p-2 text-right {{ (int) $snapshot->discount_leakage_idr > 0 ? 'text-rose-600' : '' }}">{{ number_format((int) $snapshot->discount_leakage_idr) }}</td><td class="p-2 text-right">{{ number_format((float) $snapshot->avg_discount_percent, 1) }}</td><td class="p-2 text-right">{{ number_format((float) $snapshot->promo_effectiveness_percent, 1) }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
