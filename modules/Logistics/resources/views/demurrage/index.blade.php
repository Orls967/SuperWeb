<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-slate-100 leading-tight">Demurrage &amp; Detention</h2></x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>@endif

        @if($canManage)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <form method="POST" action="{{ route('logistics.dd.tariffs.store') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-2 gap-2 text-xs">
                    @csrf
                    <div class="col-span-2 font-bold text-slate-100 text-sm">Tarif Baru</div>
                    <select name="kind" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs"><option value="demurrage">Demurrage</option><option value="detention">Detention</option></select>
                    <select name="location_id" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs"><option value="">Semua lokasi</option>@foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select>
                    <input name="size_type" placeholder="Ukuran (22G1, kosong = semua)" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                    <input name="free_days" type="number" min="0" value="3" required placeholder="Free days" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                    <input name="rate_per_day_idr" type="number" min="1" required placeholder="Tarif/hari (Rp)" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                    <input name="escalation_after_days" type="number" min="1" placeholder="Eskalasi setelah N hari berbayar" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                    <input name="escalated_rate_per_day_idr" type="number" min="1" placeholder="Tarif eskalasi/hari" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                    <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold">Simpan Tarif</button>
                </form>
                <form method="POST" action="{{ route('logistics.dd.start') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-2 gap-2 text-xs">
                    @csrf
                    <div class="col-span-2 font-bold text-slate-100 text-sm">Mulai Hitungan</div>
                    <input name="container_number" required placeholder="Nomor kontainer" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                    <input name="tracking_number" required placeholder="Nomor resi" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                    <select name="location_id" required class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">@foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select>
                    <select name="kind" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs"><option value="demurrage">Demurrage (di terminal)</option><option value="detention">Detention (di luar terminal)</option></select>
                    <button class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold col-span-2">Mulai</button>
                </form>
            </div>
            <form method="POST" action="{{ route('logistics.dd.invoice') }}">@csrf<button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold">Terbitkan Invoice D&amp;D</button></form>
        @endif

        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-x-auto">
            <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Tarif</h3></div>
            <table class="w-full text-left text-xs text-slate-300"><thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px]"><tr><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Lokasi</th><th class="px-4 py-3">Ukuran</th><th class="px-4 py-3">Free</th><th class="px-4 py-3">Tarif/hari</th><th class="px-4 py-3">Eskalasi</th></tr></thead>
                <tbody class="divide-y divide-slate-700/40">@forelse($tariffs as $t)<tr><td class="px-4 py-3">{{ $t->kind }}</td><td class="px-4 py-3">{{ $t->location?->name ?? 'Semua' }}</td><td class="px-4 py-3">{{ $t->size_type ?? 'Semua' }}</td><td class="px-4 py-3">{{ $t->free_days }} hari</td><td class="px-4 py-3">Rp {{ number_format($t->rate_per_day_idr, 0, ',', '.') }}</td><td class="px-4 py-3">{{ $t->escalation_after_days ? 'setelah '.$t->escalation_after_days.' hari: Rp '.number_format($t->escalated_rate_per_day_idr, 0, ',', '.') : '—' }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada tarif.</td></tr>@endforelse</tbody></table>
        </section>

        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-x-auto">
            <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Hitungan Kontainer</h3></div>
            <table class="w-full text-left text-xs text-slate-300"><thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px]"><tr><th class="px-4 py-3">Kontainer</th><th class="px-4 py-3">Resi</th><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Lokasi</th><th class="px-4 py-3">Mulai</th><th class="px-4 py-3">Hari Berbayar</th><th class="px-4 py-3">Diakrual</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-700/40">@forelse($dwells as $d)<tr><td class="px-4 py-3 font-mono text-amber-300">{{ $d->container?->container_number }}</td><td class="px-4 py-3 font-mono">{{ $d->shipment?->tracking_number }}</td><td class="px-4 py-3">{{ $d->kind }}</td><td class="px-4 py-3">{{ $d->location?->name }}</td><td class="px-4 py-3">{{ $d->started_at->format('d/m H:i') }}</td><td class="px-4 py-3">{{ $d->billable_days }}</td><td class="px-4 py-3">Rp {{ number_format($d->accrued_amount_idr, 0, ',', '.') }}</td><td class="px-4 py-3">{{ $d->status }}{{ $d->invoice ? ' • '.$d->invoice->invoice_number : '' }}</td><td class="px-4 py-3">@if($canManage && $d->isOpen())<form method="POST" action="{{ route('logistics.dd.end', $d->id) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 font-semibold">Tutup</button></form>@endif</td></tr>@empty<tr><td colspan="9" class="px-4 py-6 text-center text-slate-400">Belum ada hitungan.</td></tr>@endforelse</tbody></table>
        </section>
    </div>
</x-app-layout>
