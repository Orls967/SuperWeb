<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Carrier Subkontrak</h2>
            <a href="{{ route('logistics.margins') }}" class="text-xs px-3 py-1 rounded-full bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">Laporan Margin</a>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>@endif

        @if($canManage)
            <form method="POST" action="{{ route('logistics.carriers.store') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-1 md:grid-cols-5 gap-2">
                @csrf
                <input name="code" required placeholder="Kode (mis. CRR-ALFA)" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
                <input name="name" required placeholder="Nama carrier" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
                <select name="mode" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm"><option value="">Semua moda</option><option value="road">Darat</option><option value="sea">Laut</option><option value="air">Udara</option></select>
                <input name="payment_terms_days" type="number" min="0" value="7" required class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm" title="Termin pembayaran (hari)">
                <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold">Tambah Carrier</button>
            </form>
        @endif

        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Daftar Carrier &amp; Utang</h3></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px]"><tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Termin</th><th class="px-4 py-3">Leg</th><th class="px-4 py-3">Utang Belum Dibayar</th><th class="px-4 py-3">Sudah Dibayar</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-700/40">
                    @forelse($carriers as $c)
                        <tr>
                            <td class="px-4 py-3 font-mono text-amber-300">{{ $c->code }}</td><td class="px-4 py-3">{{ $c->name }}</td><td class="px-4 py-3">{{ $c->payment_terms_days }} hari</td>
                            <td class="px-4 py-3">{{ $c->legs_count }}</td>
                            <td class="px-4 py-3 font-mono">Rp {{ number_format((int) $c->accrued_unpaid_idr, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 font-mono">Rp {{ number_format((int) $c->paid_total_idr, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">@if($canManage)<form method="POST" action="{{ route('logistics.carriers.pay', $c) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Bayar Jatuh Tempo</button></form>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada carrier.</td></tr>
                    @endforelse
                </tbody></table></div>
        </section>

        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Leg Berjalan</h3></div>
            <div class="divide-y divide-slate-700/40">
                @forelse($legs as $leg)
                    <div class="p-4 text-xs text-slate-300 space-y-2">
                        <div><span class="font-mono text-amber-300">{{ $leg->shipment?->tracking_number }}</span> &bull; leg {{ $leg->leg_sequence }} &bull; {{ $leg->origin?->city }} &rarr; {{ $leg->destination?->city }} &bull; {{ $leg->carrier ? $leg->carrier->name.' (Rp '.number_format($leg->carrier_cost_idr, 0, ',', '.').')' : 'armada sendiri' }}</div>
                        <div class="flex flex-wrap gap-2">
                            @if($canManage)
                                <form method="POST" action="{{ route('logistics.legs.assign-carrier', $leg->id) }}" class="flex gap-2">
                                    @csrf
                                    <select name="carrier_id" required class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs"><option value="">Carrier...</option>@foreach($carriers as $c)<option value="{{ $c->id }}" @selected($leg->carrier_id === $c->id)>{{ $c->name }}</option>@endforeach</select>
                                    <input name="cost_idr" type="number" min="1" required placeholder="Biaya (Rp)" value="{{ $leg->carrier_cost_idr ?: '' }}" class="w-32 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                    <button class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold">Subkontrakkan</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('logistics.legs.complete', $leg->id) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 font-semibold">Selesaikan Leg</button></form>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Tidak ada leg berjalan.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
