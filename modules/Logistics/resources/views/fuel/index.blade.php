<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">BBM &amp; Biaya Truk</h2>
            <div class="flex gap-2 text-xs">
                <span class="px-3 py-1 rounded-full bg-cyan-500/20 text-cyan-300 border border-cyan-500/30" id="month-cost">Bulan ini Rp {{ number_format($monthCost, 0, ',', '.') }}</span>
                <a href="{{ route('logistics.fuel.index', ['anomaly' => $onlyAnomaly ? 0 : 1]) }}" class="px-3 py-1 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">{{ $anomalyCount }} anomali</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="p-4 rounded-xl bg-amber-950/80 border border-amber-500/50 text-amber-200 text-sm">{{ session('warning') }}</div>@endif
        @if(session('error'))<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('logistics.fuel.store') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
            @csrf
            <select name="truck_id" required class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs col-span-2 md:col-span-1"><option value="">Truk...</option>@foreach($trucks as $t)<option value="{{ $t->id }}">{{ $t->plate_number }} ({{ number_format($t->odometer_m / 1000, 1, ',', '.') }} km)</option>@endforeach</select>
            <input name="liters" type="number" step="0.001" min="0.001" required placeholder="Liter (isi penuh)" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
            <input name="price_per_liter_idr" type="number" min="1" required placeholder="Harga/liter (Rp)" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
            <input name="odometer_km" type="number" step="0.1" min="0" required placeholder="Odometer (km)" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
            <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold col-span-2 md:col-span-1">Catat BBM</button>
        </form>

        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px]"><tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Truk</th><th class="px-4 py-3">Liter</th><th class="px-4 py-3">Biaya</th><th class="px-4 py-3">Jarak</th><th class="px-4 py-3">km/l</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-700/40">
                    @forelse($logs as $l)
                        <tr data-fuel="{{ $l->id }}">
                            <td class="px-4 py-3">{{ $l->filled_at->format('d/m H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-amber-300">{{ $l->truck?->plate_number }}</td>
                            <td class="px-4 py-3">{{ $l->litersFormatted() }}</td>
                            <td class="px-4 py-3">Rp {{ number_format($l->total_cost_idr, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">{{ $l->distance_m !== null ? number_format($l->distance_m / 1000, 1, ',', '.').' km' : '—' }}</td>
                            <td class="px-4 py-3">{{ $l->km_per_liter_x100 !== null ? number_format($l->km_per_liter_x100 / 100, 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3">@if($l->is_anomaly)<span class="px-2 py-0.5 rounded border border-rose-600 bg-rose-900/40 text-rose-300" title="{{ $l->anomaly_note }}">Anomali {{ number_format($l->deviation_bp / 100, 1, ',', '.') }}%</span>@else <span class="text-slate-500">normal</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada catatan BBM.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-app-layout>
