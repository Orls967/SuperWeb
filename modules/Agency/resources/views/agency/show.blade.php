<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $agent->name }} ({{ $agent->code }})</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                <p class="text-sm text-indigo-800">Status {{ $agent->status }} · Jenis {{ $agent->kind }} · Upline {{ $agent->parent?->code ?? '—' }} · Maks override {{ $agent->max_downline_levels }} level</p>
                <a href="{{ route('agency.index') }}" class="text-sm text-indigo-700 underline">← Agensi</a>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Downline</p><p class="text-xl font-semibold">{{ $agent->children->count() }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Skema</p><p class="text-xl font-semibold">{{ $agent->schemes->count() }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Akrual</p><p class="text-xl font-semibold">{{ $agent->accruals->count() }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow text-sm"><p class="text-gray-500">Saldo statement</p><p class="text-xl font-semibold">{{ number_format((int) $statement->closing_balance_idr) }}</p></div>
            </div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">45.3 Skema komisi</h3>
                <form method="POST" action="{{ route('agency.schemes.store', $agent) }}" class="mt-3 grid gap-2 md:grid-cols-5 text-sm">
                    @csrf
                    <input name="code" placeholder="Kode skema" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                    <select name="basis" class="rounded border-gray-300"><option value="flat">Flat</option><option value="percent">Persentase</option><option value="slab">Slab</option><option value="target_bonus">Bonus target</option></select>
                    <input name="flat_amount_idr" type="number" min="0" placeholder="Flat IDR" class="rounded border-gray-300"><input name="rate_percent" type="number" step="0.01" min="0" max="100" placeholder="Rate %" class="rounded border-gray-300">
                    <select name="scope" class="rounded border-gray-300"><option value="all">Semua</option><option value="sku">SKU</option><option value="channel">Channel</option></select>
                    <input name="scope_ref" placeholder="Ref scope" class="rounded border-gray-300">
                    <input name="level" type="number" min="0" max="5" value="0" required class="w-16 rounded border-gray-300" title="0=direct, 1+=override">
                    <input name="override_rate_percent" type="number" step="0.01" min="0" max="100" placeholder="Override %" class="rounded border-gray-300">
                    <input name="target_amount_idr" type="number" min="0" placeholder="Target bonus" class="rounded border-gray-300"><input name="bonus_amount_idr" type="number" min="0" placeholder="Bonus IDR" class="rounded border-gray-300">
                    <input name="valid_from" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300"><input name="valid_until" type="date" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Simpan skema</button>
                </form>
                <div class="mt-3 space-y-1 text-sm">@foreach ($agent->schemes as $scheme)<div class="rounded border p-2">{{ $scheme->code }} — {{ $scheme->basis }} · level {{ $scheme->level }} · rate {{ $scheme->rate_percent }}% / flat {{ number_format($scheme->flat_amount_idr) }} · {{ $scheme->scope }} · {{ $scheme->is_active ? 'aktif' : 'nonaktif' }}</div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">45.8 Statement komisi</h3>
                <div class="mt-3 grid grid-cols-4 gap-3 text-sm">
                    <div class="rounded border p-3"><p class="text-gray-500">Opening</p><p class="font-semibold">{{ number_format((int) $statement->opening_balance_idr) }}</p></div>
                    <div class="rounded border p-3"><p class="text-gray-500">Akrual</p><p class="font-semibold text-emerald-600">{{ number_format((int) $statement->accrued_idr) }}</p></div>
                    <div class="rounded border p-3"><p class="text-gray-500">Clawback</p><p class="font-semibold text-rose-600">{{ number_format((int) $statement->clawback_idr) }}</p></div>
                    <div class="rounded border p-3"><p class="text-gray-500">Dibayar</p><p class="font-semibold">{{ number_format((int) $statement->paid_idr) }}</p></div>
                </div>
                <p class="mt-3 text-sm">Saldo penutup: <strong>{{ number_format((int) $statement->closing_balance_idr) }}</strong> (periode {{ $statement->period }})</p>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Kontrak &amp; hirarki</h3>
                <div class="mt-3 space-y-1 text-sm">
                    @foreach ($agent->contracts as $contract)
                        <div>{{ $contract->status }} · {{ $contract->territory_scope ?? '—' }} · {{ $contract->exclusive ? 'eksklusif' : 'non-eksklusif' }} · {{ $contract->valid_from?->toDateString() }} → {{ $contract->valid_until?->toDateString() ?? '∞' }}</div>
                    @endforeach
                    @if ($agent->children->isNotEmpty())
                        <div class="mt-2">Downline: {{ $agent->children->pluck('code')->join(', ') }}</div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
