@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="aviationFleetApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
                        Aircraft Maintenance & Airworthiness
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 177 • Integrasi Modul Asset</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Armada Pesawat & Kalender Perawatan</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Pelacakan jam terbang kumulatif, siklus pendaratan, batas interval A-Check, dan rilis kelaikan terbang.
                </p>
            </div>
            <div>
                <a href="{{ route('aviation.ops.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Pusat Kontrol OCC
                </a>
            </div>
        </div>

        {{-- Metrics Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Total Armada</span>
                <span class="text-2xl sm:text-3xl font-black text-white font-mono mt-2 block">{{ $stats['total_aircraft'] ?? 0 }}</span>
                <span class="text-[11px] text-indigo-400 font-medium mt-1 block">Pesawat Terdaftar</span>
            </div>
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Aktif Operasional</span>
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-2 block">{{ $stats['active'] ?? 0 }}</span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 block">Laik Terbang</span>
            </div>
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Dalam Perawatan</span>
                <span class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-2 block">{{ $stats['maintenance'] ?? 0 }}</span>
                <span class="text-[11px] text-amber-400 font-medium mt-1 block">Hanggar Maintenance</span>
            </div>
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Grounded</span>
                <span class="text-2xl sm:text-3xl font-black text-rose-400 font-mono mt-2 block">{{ $stats['grounded'] ?? 0 }}</span>
                <span class="text-[11px] text-rose-400 font-medium mt-1 block">Perlu Sertifikasi Ulang</span>
            </div>
        </div>

        {{-- Aircraft Table --}}
        <div class="bg-slate-800/50 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base sm:text-lg font-bold text-white">Daftar Inventaris Armada Pesawat</h2>
                <span class="text-xs text-slate-400">Total: {{ $aircrafts->total() ?? $aircrafts->count() }} Data</span>
            </div>

            @if($aircrafts->isEmpty())
                <div class="text-center py-12 border border-dashed border-slate-700 rounded-xl">
                    <span class="text-4xl block mb-2">✈️</span>
                    <h3 class="text-sm font-semibold text-white">Belum Ada Pesawat Terdaftar</h3>
                    <p class="text-xs text-slate-400 mt-1">Jalankan seeder armada untuk mengisi registrasi PK-***.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-slate-900/60 text-slate-400 border-b border-slate-700/60">
                            <tr>
                                <th class="p-3">Registrasi</th>
                                <th class="p-3">Tipe ICAO</th>
                                <th class="p-3">Model</th>
                                <th class="p-3">Operator</th>
                                <th class="p-3">Kapasitas Kursi (Y/J)</th>
                                <th class="p-3">Jam Terbang</th>
                                <th class="p-3">Siklus</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($aircrafts as $ac)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3 font-mono font-bold text-indigo-300">{{ $ac->registration_mark }}</td>
                                    <td class="p-3 font-mono">{{ $ac->icao_type }}</td>
                                    <td class="p-3">{{ $ac->model_name }}</td>
                                    <td class="p-3">{{ $ac->operator->name ?? '-' }}</td>
                                    <td class="p-3 font-mono">{{ $ac->cabin_y_capacity }} Y / {{ $ac->cabin_j_capacity }} J</td>
                                    <td class="p-3 font-mono">{{ number_format($ac->total_flight_minutes / 60, 1) }} jam</td>
                                    <td class="p-3 font-mono">{{ number_format($ac->total_flight_cycles) }}</td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                                            @if($ac->status === 'active') bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                            @elseif($ac->status === 'maintenance') bg-amber-500/20 text-amber-400 border border-amber-500/30
                                            @else bg-rose-500/20 text-rose-400 border border-rose-500/30 @endif">
                                            {{ strtoupper($ac->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-700/60 bg-slate-900/50 text-[11px] text-slate-400">
                            <tr>
                                <td colspan="7" class="py-2.5 px-4 text-right font-medium">Total Pesawat: {{ count($aircraft) }} armada aktif</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
        {{-- Fleet End --}}
    </div>
</div>

<script>
function aviationFleetApp() {
    return {};
}
</script>
@endsection

