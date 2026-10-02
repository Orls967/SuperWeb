<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Bea Cukai (PIB / PEB)</h2>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">SIMULASI, bukan nasihat kepabeanan</span>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="p-4 rounded-xl bg-amber-950/80 border border-amber-500/50 text-amber-200 text-sm">{{ session('warning') }}</div>@endif
        @if(session('error'))<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('logistics.customs.store') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-1 md:grid-cols-6 gap-2 text-xs">
            @csrf
            <div class="md:col-span-6 font-bold text-slate-100 text-sm">Ajukan Dokumen</div>
            <input name="tracking_number" required placeholder="Nomor resi" value="{{ old('tracking_number') }}" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
            <select name="type" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs"><option value="PIB">PIB (impor)</option><option value="PEB">PEB (ekspor)</option></select>
            <label class="flex items-center gap-2 text-slate-300"><input type="checkbox" name="has_api" value="1"> Importir ber-API</label>
            <input name="lines[0][hs_code]" required placeholder="HS 8 digit" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
            <input name="lines[0][value_idr]" type="number" min="1" required placeholder="Nilai pabean (Rp)" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
            <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold">Ajukan</button>
        </form>

        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="divide-y divide-slate-700/40">
                @forelse($declarations as $d)
                    <div class="p-4 text-xs text-slate-300 space-y-2" data-declaration="{{ $d->declaration_number }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-amber-300 font-semibold">{{ $d->declaration_number }}</span>
                            <span class="px-2 py-0.5 rounded border {{ $d->lane === 'red' ? 'border-rose-600 bg-rose-900/40 text-rose-300' : 'border-emerald-600 bg-emerald-900/40 text-emerald-300' }}">jalur {{ $d->lane }}</span>
                            <span>{{ $d->status }}</span><span class="font-mono">{{ $d->shipment?->tracking_number }}</span>
                            <span>Nilai Rp {{ number_format($d->customs_value_idr, 0, ',', '.') }}</span>
                        </div>
                        <div>BM Rp {{ number_format($d->bm_idr, 0, ',', '.') }} &bull; PPN Rp {{ number_format($d->ppn_idr, 0, ',', '.') }} &bull; PPh 22 Rp {{ number_format($d->pph22_idr, 0, ',', '.') }} &bull; <strong>Total Rp {{ number_format($d->total_duty_idr, 0, ',', '.') }}</strong> {{ $d->paid_at ? '(dibayar)' : '(belum dibayar)' }}</div>
                        @if($d->hold_reason)<div class="text-rose-300">{{ $d->hold_reason }}</div>@endif
                        <div class="flex flex-wrap gap-2">
                            @if(! $d->paid_at && $d->shipment?->shipper_id === $userId)
                                <form method="POST" action="{{ route('logistics.customs.pay', $d->id) }}" class="flex gap-2">@csrf<input name="pin" type="password" required placeholder="PIN dompet" class="w-28 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs"><button class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Bayar</button></form>
                            @endif
                            @if($isOfficer && $d->status !== 'cleared')
                                <form method="POST" action="{{ route('logistics.customs.clear', $d->id) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold">Loloskan (SPPB)</button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Belum ada dokumen.</div>
                @endforelse
            </div>
        </section>

        @if($isOfficer)
            <form method="POST" action="{{ route('logistics.customs.tariffs.store') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                @csrf
                <div class="col-span-full font-bold text-slate-100 text-sm">Tarif HS (basis poin: 500 = 5%)</div>
                <input name="hs_code" required placeholder="HS 8 digit" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                <input name="description" required placeholder="Uraian barang" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                <input name="bm_bp" type="number" min="0" value="500" required placeholder="BM bp" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                <input name="ppn_bp" type="number" min="0" value="1100" required placeholder="PPN bp" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                <input name="pph22_api_bp" type="number" min="0" value="250" required placeholder="PPh22 API bp" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                <input name="pph22_non_api_bp" type="number" min="0" value="750" required placeholder="PPh22 non-API bp" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                <label class="flex items-center gap-2 text-slate-300"><input type="checkbox" name="requires_inspection" value="1"> Lartas (jalur merah)</label>
                <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold">Simpan Tarif</button>
            </form>
            <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300"><thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px]"><tr><th class="px-4 py-3">HS</th><th class="px-4 py-3">Uraian</th><th class="px-4 py-3">BM</th><th class="px-4 py-3">PPN</th><th class="px-4 py-3">PPh22 API/non</th><th class="px-4 py-3">Lartas</th></tr></thead>
                    <tbody class="divide-y divide-slate-700/40">@forelse($tariffs as $t)<tr><td class="px-4 py-3 font-mono text-amber-300">{{ $t->hs_code }}</td><td class="px-4 py-3">{{ $t->description }}</td><td class="px-4 py-3">{{ $t->bm_bp / 100 }}%</td><td class="px-4 py-3">{{ $t->ppn_bp / 100 }}%</td><td class="px-4 py-3">{{ $t->pph22_api_bp / 100 }}% / {{ $t->pph22_non_api_bp / 100 }}%</td><td class="px-4 py-3">{{ $t->requires_inspection ? 'Ya' : '—' }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada tarif HS.</td></tr>@endforelse</tbody></table>
            </section>
        @endif
    </div>
</x-app-layout>
