<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $distributor->name }} ({{ $distributor->code }})</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                <p class="text-sm text-indigo-800">Status {{ $distributor->status }} · Tier {{ $distributor->tier }} · Diskon {{ number_format($discount, 1) }}% · Termin {{ $distributor->payment_terms_days }} hari</p>
                <a href="{{ route('distribution.index') }}" class="text-sm text-indigo-700 underline">← Jaringan</a>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Limit kredit</p><p class="text-xl font-semibold">{{ number_format($distributor->credit_limit_idr) }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Eksposur</p><p class="text-xl font-semibold {{ $distributor->isBlocked() ? 'text-rose-600' : '' }}">{{ number_format($distributor->credit_exposure_idr) }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Tersedia</p><p class="text-xl font-semibold">{{ number_format($distributor->availableCredit()) }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Jumlah outlet</p><p class="text-xl font-semibold">{{ $distributor->outlets->count() }}</p></div>
            </div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">42.4 Aging piutang</h3>
                <div class="mt-3 grid grid-cols-4 gap-3 text-sm">
                    @foreach ($aging as $bucket => $amount)
                        <div class="rounded border p-3"><p class="text-gray-500">{{ $bucket }} hari</p><p class="font-semibold">{{ number_format($amount) }}</p></div>
                    @endforeach
                </div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">No.</th><th class="p-2 text-left">Tipe</th><th class="p-2 text-right">Nilai</th><th class="p-2 text-right">Dibayar</th><th class="p-2 text-right">Denda</th><th class="p-2 text-right">Sisa</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Jatuh tempo</th></tr></thead><tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-t"><td class="p-2">{{ $invoice->number }}</td><td class="p-2">{{ $invoice->source_type }}</td>
                        <td class="p-2 text-right">{{ number_format($invoice->amount_idr) }}</td><td class="p-2 text-right">{{ number_format($invoice->paid_amount_idr) }}</td>
                        <td class="p-2 text-right">{{ number_format($invoice->denda_idr) }}</td><td class="p-2 text-right">{{ number_format($invoice->openAmount()) }}</td>
                        <td class="p-2">{{ $invoice->status }}</td><td class="p-2">{{ $invoice->due_date?->toDateString() }}</td></tr>
                    @empty
                        <tr><td colspan="8" class="p-2 text-gray-500">Belum ada tagihan.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">42.7 Outlet &amp; 42.2 Coverage</h3>
                <form method="POST" action="{{ route('distribution.outlets.store', $distributor) }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <input name="code" placeholder="Kode outlet" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                    <select name="segment" class="rounded border-gray-300">@foreach ($outletSegments as $segment)<option value="{{ $segment }}">{{ $segment }}</option>@endforeach</select>
                    <input name="city" placeholder="Kota" class="rounded border-gray-300"><input name="territory_id" type="number" placeholder="Territory ID" class="w-28 rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Tambah outlet</button>
                </form>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($distributor->outlets as $outlet)
                        <div class="rounded border p-3 text-sm">{{ $outlet->code }} — {{ $outlet->name }} · {{ $outlet->segment }}{{ $outlet->city ? ' · '.$outlet->city : '' }}</div>
                    @endforeach
                    @foreach ($distributor->coverages as $coverage)
                        <div class="rounded border p-3 text-sm">{{ $coverage->territory?->name }} — {{ $coverage->exclusive ? 'eksklusif' : 'non-eksklusif' }} · s/d {{ $coverage->valid_until?->toDateString() ?? '∞' }}</div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">42.3 Jaminan onboarding</h3>
                <form method="POST" action="{{ route('distribution.securities.store', $distributor) }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <select name="kind" class="rounded border-gray-300"><option value="bank_guarantee">Bank garansi</option><option value="deposit">Deposit</option></select>
                    <input name="amount_idr" type="number" min="1" required placeholder="Nilai" class="w-40 rounded border-gray-300"><input name="reference" placeholder="Referensi" class="rounded border-gray-300"><input name="expires_at" type="date" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Catat jaminan</button>
                </form>
                <div class="mt-3 space-y-1 text-sm">@foreach ($distributor->securities as $security)<div>{{ $security->kind }} · {{ number_format($security->amount_idr) }} · {{ $security->status }}{{ $security->expires_at ? ' · s/d '.$security->expires_at->toDateString() : '' }}{{ $security->isExpired() ? ' · KEDALUWARSA' : '' }}</div>@endforeach</div>
            </section>
        </div>
    </div>
</x-app-layout>
