@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="aviationOpsApp()">
    {{-- Toast Notification --}}
    <div x-show="toast.visible" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-50 flex items-center p-4 rounded-xl shadow-2xl border"
         :class="toast.type === 'success' ? 'bg-emerald-950 border-emerald-500 text-emerald-200' : 'bg-rose-950 border-rose-500 text-rose-200'"
         style="display: none;">
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✈️' : '⚠️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-sky-500/20 text-sky-400 border border-sky-500/30">
                        Flight Operations Control Center (OCC)
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 177 • Permenhub PM 89/2015</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Pusat Kendali Operasi Penerbangan</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Pemantauan jadwal penerbangan real-time, status rotasi armada, dan peringatan kelaikan udara.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('aviation.dispatch.index') }}" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs sm:text-sm font-semibold transition-colors shadow-lg shadow-sky-600/30">
                    Buka Dispatch Board &rarr;
                </a>
            </div>
        </div>

        {{-- Metrics Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Total Penerbangan</span>
                <span class="text-2xl sm:text-3xl font-black text-white font-mono mt-2 block">{{ $stats['total_flights'] ?? 0 }}</span>
                <span class="text-[11px] text-sky-400 font-medium mt-1 block">Jadwal Aktif</span>
            </div>
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Pesawat Di Udara</span>
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-2 block">{{ $stats['departed_flights'] ?? 0 }}</span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 block">Status Departed</span>
            </div>
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Kelaikan Siap</span>
                <span class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-2 block">{{ $stats['cleared_flights'] ?? 0 }}</span>
                <span class="text-[11px] text-amber-400 font-medium mt-1 block">Airworthy Cleared</span>
            </div>
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Dibatalkan (IRROPS)</span>
                <span class="text-2xl sm:text-3xl font-black text-rose-400 font-mono mt-2 block">{{ $stats['cancelled_flights'] ?? 0 }}</span>
                <span class="text-[11px] text-rose-400 font-medium mt-1 block">Erupsi / Cuaca Buruk</span>
            </div>
        </div>

        {{-- Active Flights Table --}}
        <div class="bg-slate-800/50 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base sm:text-lg font-bold text-white">Papan Operasi Penerbangan Hari Ini</h2>
                <span class="text-xs text-slate-400">Total: {{ $flights->count() }} Data Ditampilkan</span>
            </div>

            @if($flights->isEmpty())
                {{-- Empty State --}}
                <div class="text-center py-12 border border-dashed border-slate-700 rounded-xl">
                    <span class="text-4xl block mb-2">🛫</span>
                    <h3 class="text-sm font-semibold text-white">Tidak Ada Penerbangan Terjadwal</h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                        Belum ada penerbangan yang dibuat atau dijadwalkan untuk hari ini.
                    </p>
                </div>
            @else
                {{-- Data Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-slate-900/60 text-slate-400 border-b border-slate-700/60">
                            <tr>
                                <th class="p-3">No. Penerbangan</th>
                                <th class="p-3">Operator</th>
                                <th class="p-3">Rute</th>
                                <th class="p-3">Waktu Berangkat</th>
                                <th class="p-3">Waktu Tiba</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($flights as $flight)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3 font-mono font-bold text-sky-300">{{ $flight->flight_number }}</td>
                                    <td class="p-3">{{ $flight->operator->name ?? '-' }}</td>
                                    <td class="p-3 font-mono">
                                        {{ $flight->origin->iata_code ?? '---' }} &rarr; {{ $flight->destination->iata_code ?? '---' }}
                                    </td>
                                    <td class="p-3 text-slate-300">{{ $flight->departure_time->format('H:i d/m') }}</td>
                                    <td class="p-3 text-slate-300">{{ $flight->arrival_time->format('H:i d/m') }}</td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                                            @if($flight->status === 'departed') bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                            @elseif($flight->status === 'airworthy_cleared') bg-sky-500/20 text-sky-400 border border-sky-500/30
                                            @elseif($flight->status === 'cancelled') bg-rose-500/20 text-rose-400 border border-rose-500/30
                                            @else bg-slate-700 text-slate-300 @endif">
                                            {{ strtoupper($flight->status) }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <a href="{{ route('aviation.dispatch.index') }}" class="text-xs text-sky-400 hover:text-sky-300 font-semibold underline">
                                            Rincian
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-700/60 bg-slate-900/40 text-[11px] text-slate-400">
                            <tr>
                                <td colspan="7" class="py-2.5 px-4 text-right font-medium">Total Penerbangan Hari Ini: {{ count($flights) }} jadwal</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
        {{-- Ops End --}}
    </div>
</div>
    </div>
</div>

<script>
function aviationOpsApp() {
    return {
        toast: { visible: false, title: '', message: '', type: 'success' },
        showToast(title, message, type = 'success') {
            this.toast = { visible: true, title, message, type };
            setTimeout(() => { this.toast.visible = false; }, 4000);
        }
    };
}
</script>
@endsection

