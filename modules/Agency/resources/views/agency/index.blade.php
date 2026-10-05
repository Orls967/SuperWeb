<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Agensi &amp; Komisi</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">45.1–45.8 — agen &amp; hirarki, skema komisi, atribusi, akrual hold + clawback, payout four-eyes, statement.</div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">45.1 Agen &amp; hirarki</h3>
                <form method="POST" action="{{ route('agency.agents.store') }}" class="mt-3 grid gap-2 md:grid-cols-5 text-sm">
                    @csrf
                    <input name="code" placeholder="Kode" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                    <select name="kind" class="rounded border-gray-300"><option value="sales_agent">Agen penjualan</option><option value="broker">Broker</option><option value="reseller">Reseller</option><option value="affiliate">Afiliasi</option><option value="sole_agent">Agen tunggal</option></select>
                    <select name="parent_id" class="rounded border-gray-300"><option value="">Upline (opsional)</option>@foreach ($agents as $agentOption)<option value="{{ $agentOption->id }}">{{ $agentOption->code }}</option>@endforeach</select>
                    <input name="region_code" placeholder="Wilayah" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Daftar</button>
                </form>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Kode</th><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Upline</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Aksi</th></tr></thead><tbody>
                    @foreach ($agents as $agent)
                        <tr class="border-t"><td class="p-2"><a class="text-indigo-700 underline" href="{{ route('agency.show', $agent) }}">{{ $agent->code }}</a></td>
                        <td class="p-2">{{ $agent->name }}</td><td class="p-2">{{ $agent->kind }}</td><td class="p-2">{{ $agent->parent?->code ?? '—' }}</td>
                        <td class="p-2 {{ $agent->status === 'active' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $agent->status }}</td>
                        <td class="p-2 flex gap-2">
                            @foreach (['active' => 'Aktifkan', 'suspended' => 'Tangguh', 'terminated' => 'Akhiri'] as $to => $label)
                                @if ($agent->status !== $to && $agent->status !== 'terminated')<form method="POST" action="{{ route('agency.agents.transition', $agent) }}">@csrf<input type="hidden" name="to" value="{{ $to }}"><button class="text-indigo-700 underline">{{ $label }}</button></form>@endif
                            @endforeach
                        </td></tr>
                    @endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between"><h3 class="text-lg font-semibold">45.5/45.7 Akrual, hold &amp; payout</h3>
                    <form method="POST" action="{{ route('agency.accruals.release') }}">@csrf<button class="rounded bg-amber-600 px-3 py-1.5 text-sm text-white">Lepas hold lewat retur</button></form>
                </div>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('agency.accruals.store') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <select name="agent_id" required class="rounded border-gray-300"><option value="">Agen</option>@foreach ($agents as $agentOption)<option value="{{ $agentOption->id }}">{{ $agentOption->code }}</option>@endforeach</select>
                        <input name="reference_id" placeholder="Ref order" required class="rounded border-gray-300"><input name="base_amount_idr" type="number" min="1" required placeholder="Nilai" class="w-32 rounded border-gray-300"><input name="hold_days" type="number" min="0" value="30" required class="w-20 rounded border-gray-300" title="Hold hari">
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Akrual</button>
                    </form>
                    <form method="POST" action="{{ route('agency.accruals.clawback') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <input name="accrual_id" type="number" min="1" required placeholder="Accrual ID" class="w-24 rounded border-gray-300"><input name="amount_idr" type="number" min="1" required placeholder="Nilai balik" class="w-32 rounded border-gray-300"><input name="reason" placeholder="Alasan retur" required class="rounded border-gray-300">
                        <button class="rounded bg-rose-600 px-3 py-2 text-white">45.6 Clawback</button>
                    </form>
                </div>
                <div class="mt-3 space-y-2">
                    @foreach ($accruals as $accrual)
                        <div class="rounded border p-3 text-sm">{{ $accrual->agent?->code }} · {{ $accrual->reference_id }} · {{ number_format($accrual->amount_idr) }} · {{ $accrual->status }} · hold s/d {{ $accrual->hold_until?->toDateString() }}</div>
                    @endforeach
                </div>
                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                    @foreach ($payouts as $payout)
                        <div class="rounded border p-3 text-sm">
                            <strong>{{ $payout->number }}</strong> — {{ $payout->period }} · {{ $payout->agent?->code }}<br>
                            gross {{ number_format($payout->gross_idr) }} − PPh {{ number_format($payout->withheld_tax_idr) }} = net {{ number_format($payout->net_idr) }} · {{ $payout->status }}
                            @if ($payout->status === 'pending')<form method="POST" action="{{ route('agency.payouts.approve', $payout) }}" class="mt-1">@csrf<button class="text-emerald-700 underline">Setujui (four-eyes)</button></form>@endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-2">
                    @foreach ($agents as $agent)
                        <form method="POST" action="{{ route('agency.payouts.store', $agent) }}" class="flex flex-wrap gap-2 text-sm">
                            @csrf
                            <span class="w-32 font-medium">{{ $agent->code }}</span>
                            <input name="period" placeholder="YYYY / YYYY-MM" value="{{ now()->format('Y') }}" required class="rounded border-gray-300">
                            <input name="withholding_rate_percent" type="number" step="0.01" min="0" max="100" value="2" class="w-24 rounded border-gray-300" title="PPh simulasi %">
                            <button class="rounded bg-indigo-600 px-3 py-1.5 text-white">Buat payout</button>
                        </form>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">45.4 Atribusi penjualan</h3>
                <form method="POST" action="{{ route('agency.attributions.store') }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <select name="agent_id" required class="rounded border-gray-300"><option value="">Agen</option>@foreach ($agents as $agentOption)<option value="{{ $agentOption->id }}">{{ $agentOption->code }}</option>@endforeach</select>
                    <input name="reference_id" placeholder="Ref lead/order" required class="rounded border-gray-300">
                    <select name="source" class="rounded border-gray-300"><option value="referral">Referral</option><option value="lead">Lead</option><option value="agent_code">Kode agen</option></select>
                    <select name="rule" class="rounded border-gray-300"><option value="last_touch">Last touch</option><option value="first_touch">First touch</option></select>
                    <input name="touched_at" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300"><input name="expires_at" type="date" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Catat</button>
                </form>
                <div class="mt-3 space-y-1 text-sm">@foreach ($attributions as $attr)<div>{{ $attr->agent?->code }} · {{ $attr->reference_id }} · {{ $attr->rule }} · {{ $attr->status }} · s/d {{ $attr->expires_at?->toDateString() ?? '∞' }}</div>@endforeach</div>
            </section>
        </div>
    </div>
</x-app-layout>
