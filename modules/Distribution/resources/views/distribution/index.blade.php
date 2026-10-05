<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Jaringan Distributor</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                <p class="text-sm text-indigo-800">42.1–42.8 — entitas, teritori, onboarding, piutang, target/tier, outlet, scorecard.</p>
                <form method="POST" action="{{ route('distribution.sweep') }}">@csrf<button class="rounded bg-rose-600 px-3 py-1.5 text-sm text-white">Sweep overdue + denda</button></form>
            </div>

            @if (count($conflicts) > 0)
                <div class="rounded-md bg-red-50 p-4 text-red-800 text-sm">
                    <strong>Konflik teritori eksklusif:</strong>
                    <ul class="list-disc pl-5">@foreach ($conflicts as $conflict)<li>{{ $conflict['territory'] }} — {{ implode(', ', $conflict['codes']) }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">42.1 Distributor &amp; hirarki</h3>
                <form method="POST" action="{{ route('distribution.distributors.store') }}" class="mt-3 grid gap-2 md:grid-cols-5 text-sm">
                    @csrf
                    <input name="code" placeholder="Kode" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                    <select name="kind" class="rounded border-gray-300"><option value="distributor">Distributor</option><option value="sub_distributor">Sub-distributor</option><option value="agent">Agen grosir</option><option value="dealer">Dealer</option></select>
                    <select name="parent_id" class="rounded border-gray-300"><option value="">Induk (opsional)</option>@foreach ($distributors as $d)<option value="{{ $d->id }}">{{ $d->code }}</option>@endforeach</select>
                    <input name="payment_terms_days" type="number" min="0" value="30" required placeholder="Termin (hari)" class="rounded border-gray-300">
                    <input name="credit_limit_idr" type="number" min="0" value="0" required placeholder="Limit kredit" class="rounded border-gray-300">
                    <input name="outlet_code" placeholder="Kode toko" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Daftar</button>
                </form>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Kode</th><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Tier</th><th class="p-2 text-left">Status</th><th class="p-2 text-right">Limit</th><th class="p-2 text-right">Eksposur</th><th class="p-2 text-left">Aksi</th></tr></thead><tbody>
                    @foreach ($distributors as $d)
                        <tr class="border-t">
                            <td class="p-2"><a class="text-indigo-700 underline" href="{{ route('distribution.show', $d) }}">{{ $d->code }}</a></td>
                            <td class="p-2">{{ $d->name }}</td><td class="p-2">{{ $d->kind }}</td><td class="p-2">{{ $d->tier }}</td>
                            <td class="p-2 {{ $d->status === 'approved' ? 'text-emerald-600' : ($d->status === 'blocked' ? 'text-rose-600' : 'text-amber-600') }}">{{ $d->status }}</td>
                            <td class="p-2 text-right">{{ number_format($d->credit_limit_idr) }}</td><td class="p-2 text-right">{{ number_format($d->credit_exposure_idr) }}</td>
                            <td class="p-2 flex gap-2">
                                @if ($d->status === 'onboarding')
                                    <form method="POST" action="{{ route('distribution.onboarding.submit', $d) }}">@csrf<button class="text-indigo-700 underline">Ajukan</button></form>
                                @endif
                                @if ($d->status === 'onboarding')
                                    <form method="POST" action="{{ route('distribution.onboarding.approve', $d) }}" class="flex gap-1">@csrf<input name="credit_limit_idr" type="number" min="0" value="50000000" class="w-28 rounded border-gray-300 text-xs"><button class="text-emerald-700 underline">Setujui</button></form>
                                @endif
                                <form method="POST" action="{{ route('distribution.tier.evaluate', $d) }}">@csrf<button class="text-amber-700 underline">Tier</button></form>
                            </td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">42.2 Teritori &amp; coverage</h3>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('distribution.territories.store') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <input name="code" placeholder="Kode wilayah" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                        <select name="level" class="rounded border-gray-300"><option value="province">Provinsi</option><option value="city">Kota</option><option value="district">Kecamatan</option></select>
                        <input name="parent_id" type="number" placeholder="Parent ID" class="w-24 rounded border-gray-300"><button class="rounded bg-indigo-600 px-3 py-2 text-white">Tambah</button>
                    </form>
                    <form method="POST" action="{{ route('distribution.coverages.store') }}" class="flex flex-wrap gap-2 text-sm">
                        @csrf
                        <select name="distributor_id" required class="rounded border-gray-300"><option value="">Distributor</option>@foreach ($distributors as $d)<option value="{{ $d->id }}">{{ $d->code }}</option>@endforeach</select>
                        <input name="territory_id" type="number" placeholder="Territory ID" required class="w-24 rounded border-gray-300">
                        <input name="valid_from" type="date" required class="rounded border-gray-300">
                        <label class="flex items-center gap-1"><input type="checkbox" name="exclusive" value="1"> Eksklusif</label>
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Pasang</button>
                    </form>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">@foreach ($territories as $territory)<div class="rounded border p-3 text-sm">{{ $territory->level }} · {{ $territory->code }} — {{ $territory->name }}</div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">42.4/42.5 Piutang, bayar, target</h3>
                <div class="mt-3 grid gap-4 lg:grid-cols-3 text-sm">
                    <form method="POST" action="{{ route('distribution.invoices.store') }}" class="space-y-2">
                        @csrf
                        <select name="distributor_id" required class="w-full rounded border-gray-300"><option value="">Distributor</option>@foreach ($distributors as $d)<option value="{{ $d->id }}">{{ $d->code }}</option>@endforeach</select>
                        <input name="amount_idr" type="number" min="1" required placeholder="Nilai tagihan" class="w-full rounded border-gray-300">
                        <input name="invoice_date" type="date" required class="w-full rounded border-gray-300"><input name="due_date" type="date" required class="w-full rounded border-gray-300">
                        <select name="source_type" class="w-full rounded border-gray-300"><option value="sales">Sales</option><option value="retur">Retur</option><option value="denda">Denda</option><option value="manual">Manual</option></select>
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Terbitkan AR</button>
                    </form>
                    <form method="POST" action="{{ route('distribution.invoices.pay') }}" class="space-y-2">
                        @csrf
                        <input name="invoice_id" placeholder="Invoice UUID" required class="w-full rounded border-gray-300">
                        <input name="amount_idr" type="number" min="1" required placeholder="Nilai bayar" class="w-full rounded border-gray-300">
                        <input name="paid_at" type="date" required class="w-full rounded border-gray-300">
                        <select name="method" class="w-full rounded border-gray-300"><option value="transfer">Transfer</option><option value="cash">Tunai</option><option value="giro">Giro</option><option value="ewallet">E-wallet</option></select>
                        <button class="rounded bg-emerald-600 px-3 py-2 text-white">Catat pembayaran</button>
                    </form>
                    <form method="POST" action="{{ route('distribution.targets.store') }}" class="space-y-2">
                        @csrf
                        <select name="distributor_id" required class="w-full rounded border-gray-300"><option value="">Distributor</option>@foreach ($distributors as $d)<option value="{{ $d->id }}">{{ $d->code }}</option>@endforeach</select>
                        <input name="product_sku" placeholder="SKU produk" required class="w-full rounded border-gray-300">
                        <div class="flex gap-2"><input name="period" placeholder="2026" value="{{ now()->format('Y') }}" required class="rounded border-gray-300"><input name="target_qty" type="number" step="0.01" min="0.01" required placeholder="Target qty" class="rounded border-gray-300">
                        <select name="basis" class="rounded border-gray-300"><option value="sell_in">Sell-in</option><option value="sell_out">Sell-out</option></select></div>
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Set target</button>
                    </form>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">42.8 Scorecard kinerja</h3>
                <form method="POST" action="{{ route('distribution.scorecards.store') }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <select name="distributor_id" required class="rounded border-gray-300"><option value="">Distributor</option>@foreach ($distributors as $d)<option value="{{ $d->id }}">{{ $d->code }}</option>@endforeach</select>
                    <input name="period" placeholder="2026" value="{{ now()->format('Y') }}" required class="rounded border-gray-300">
                    <input name="fill_rate_percent" type="number" step="0.01" min="0" max="100" placeholder="Fill rate %" class="w-28 rounded border-gray-300">
                    <input name="price_compliance_percent" type="number" step="0.01" min="0" max="100" placeholder="Kepatuhan harga %" class="w-32 rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Hitung scorecard</button>
                </form>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Distributor</th><th class="p-2 text-left">Periode</th><th class="p-2 text-right">Sell-in</th><th class="p-2 text-right">Sell-out</th><th class="p-2 text-right">Fill rate</th><th class="p-2 text-right">DSO</th><th class="p-2 text-right">Skor</th><th class="p-2 text-left">Tier disarankan</th></tr></thead><tbody>
                    @foreach ($scorecards as $sc)
                        <tr class="border-t"><td class="p-2">{{ $sc->distributor?->code }}</td><td class="p-2">{{ $sc->period }}</td>
                        <td class="p-2 text-right">{{ number_format($sc->sell_in_idr) }}</td><td class="p-2 text-right">{{ number_format($sc->sell_out_idr) }}</td>
                        <td class="p-2 text-right">{{ number_format($sc->fill_rate_percent, 1) }}</td><td class="p-2 text-right">{{ number_format($sc->dso_days, 1) }}</td>
                        <td class="p-2 text-right font-semibold">{{ number_format($sc->score, 1) }}</td><td class="p-2">{{ $sc->recommended_tier }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
