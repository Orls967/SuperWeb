@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="aviationSlotsApp()">
    {{-- Header --}}
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        IATA WSG Slot Coordination
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 177 • Algoritma A1 Certified</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Koordinasi Slot Musiman Bandara</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Aturan historis 80/20 "use it or lose it", prioritas grandfather rights, dan alokasi kapasitas per jam.
                </p>
            </div>
            <div>
                <a href="{{ route('aviation.ops.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Pusat Kontrol OCC
                </a>
            </div>
        </div>

        {{-- Form Permintaan Slot --}}
        <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <h2 class="text-base sm:text-lg font-bold text-white mb-3">Ajukan Permintaan Slot Musiman</h2>
            <form action="{{ route('aviation.slots.request') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Bandara</label>
                    <select name="airport_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs sm:text-sm text-white focus:ring-2 focus:ring-amber-500">
                        @foreach($airports as $airport)
                            <option value="{{ $airport->id }}">{{ $airport->iata_code }} - {{ $airport->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Musim IATA</label>
                    <input type="text" name="season" value="W26" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs sm:text-sm text-white font-mono" placeholder="contoh: W26 atau S27" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Jam UTC (0-23)</label>
                    <input type="number" name="allocated_hour" min="0" max="23" value="8" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs sm:text-sm text-white font-mono" required>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs sm:text-sm font-semibold transition-colors shadow-lg shadow-amber-600/30">
                        Kirim Permintaan
                    </button>
                </div>
            </form>
        </div>

        {{-- Slot List --}}
        <div class="bg-slate-800/50 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base sm:text-lg font-bold text-white">Daftar Alokasi Slot Terdaftar</h2>
                <span class="text-xs text-slate-400">Total: {{ $slots->count() }} Data</span>
            </div>

            @if($slots->isEmpty())
                <div class="text-center py-12 border border-dashed border-slate-700 rounded-xl">
                    <span class="text-4xl block mb-2">⏱️</span>
                    <h3 class="text-sm font-semibold text-white">Belum Ada Slot Terkoordinasi</h3>
                    <p class="text-xs text-slate-400 mt-1">Gunakan formulir di atas untuk mengajukan slot baru.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-slate-900/60 text-slate-400 border-b border-slate-700/60">
                            <tr>
                                <th class="p-3">Bandara</th>
                                <th class="p-3">Operator</th>
                                <th class="p-3">Musim</th>
                                <th class="p-3">Jam (UTC)</th>
                                <th class="p-3">Prioritas</th>
                                <th class="p-3">Utilisasi Hist.</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($slots as $slot)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3 font-mono font-bold text-amber-300">{{ $slot->airport->iata_code ?? '---' }}</td>
                                    <td class="p-3">{{ $slot->operator->name ?? '-' }}</td>
                                    <td class="p-3 font-mono">{{ $slot->season }}</td>
                                    <td class="p-3 font-mono">{{ sprintf('%02d:00', $slot->allocated_hour) }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $slot->priority_level === 'historic' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">
                                            {{ strtoupper($slot->priority_level) }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono">{{ number_format($slot->historical_utilization_rate / 100, 1) }}%</td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $slot->status === 'allocated' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-300' }}">
                                            {{ strtoupper($slot->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-700/60 bg-slate-900/60 text-[11px] text-slate-400">
                            <tr>
                                <td colspan="7" class="py-2.5 px-4 text-right font-medium">Rekap Slot Terkoordinasi: {{ count($slots) }} alokasi</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
        {{-- Slot Management End --}}
    </div>
</div>

<script>
function aviationSlotsApp() {
    return {};
}
</script>
@endsection

