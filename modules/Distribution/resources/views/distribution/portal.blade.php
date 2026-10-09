<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Portal Distributor — {{ $distributor->code }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                Tier {{ $distributor->tier }} · Diskon {{ number_format($discount, 1) }}% · Termin {{ $distributor->payment_terms_days }} hari · Status {{ $distributor->status }}
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Limit kredit</p><p class="text-xl font-semibold">{{ number_format($distributor->credit_limit_idr) }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Eksposur</p><p class="text-xl font-semibold {{ $distributor->isBlocked() ? 'text-rose-600' : '' }}">{{ number_format($distributor->credit_exposure_idr) }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Tersedia</p><p class="text-xl font-semibold">{{ number_format($distributor->availableCredit()) }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Outlet aktif</p><p class="text-xl font-semibold">{{ $distributor->outlets->count() }}</p></div>
            </div>

            @if ($distributor->isBlocked())
                <div class="rounded-md bg-red-50 p-4 text-red-800 text-sm">
                    Akun diblokir — tidak dapat membuat order baru sampai tagihan diselesaikan.
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Tagihan saya</h3>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">No.</th><th class="p-2 text-right">Nilai</th><th class="p-2 text-right">Dibayar</th><th class="p-2 text-right">Sisa</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Jatuh tempo</th></tr></thead><tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-t"><td class="p-2">{{ $invoice->number }}</td><td class="p-2 text-right">{{ number_format($invoice->amount_idr) }}</td><td class="p-2 text-right">{{ number_format($invoice->paid_amount_idr) }}</td><td class="p-2 text-right">{{ number_format($invoice->openAmount()) }}</td><td class="p-2 {{ $invoice->status === 'overdue' ? 'text-rose-600' : '' }}">{{ $invoice->status }}</td><td class="p-2">{{ $invoice->due_date?->toDateString() }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="p-2 text-gray-500">Belum ada tagihan.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="mt-4 grid grid-cols-4 gap-3 text-sm">
                    @foreach ($aging as $bucket => $amount)
                        <div class="rounded border p-3"><p class="text-gray-500">{{ $bucket }} hari</p><p class="font-semibold">{{ number_format($amount) }}</p></div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Target &amp; capaian</h3>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">SKU</th><th class="p-2 text-left">Periode</th><th class="p-2 text-left">Basis</th><th class="p-2 text-right">Target</th><th class="p-2 text-right">Capaian</th><th class="p-2 text-right">%</th></tr></thead><tbody>
                    @forelse ($targets as $target)
                        <tr class="border-t"><td class="p-2">{{ $target->product_sku }}</td><td class="p-2">{{ $target->period }}</td><td class="p-2">{{ $target->basis }}</td><td class="p-2 text-right">{{ number_format((float) $target->target_qty, 2) }}</td><td class="p-2 text-right">{{ number_format((float) $target->achieved_qty, 2) }}</td><td class="p-2 text-right {{ $target->achievementPercent() >= 100 ? 'text-emerald-600' : 'text-amber-600' }}">{{ number_format($target->achievementPercent(), 1) }}%</td></tr>
                    @empty
                        <tr><td colspan="6" class="p-2 text-gray-500">Belum ada target.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Outlet saya</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    @forelse ($outlets as $outlet)
                        <div class="rounded border p-3 text-sm">{{ $outlet->code }} — {{ $outlet->name }} · {{ $outlet->segment }}</div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada outlet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
