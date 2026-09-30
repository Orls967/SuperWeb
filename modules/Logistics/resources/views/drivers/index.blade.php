<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                {{ __('Pengemudi & Kru Armada (Drivers)') }}
            </h2>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                    Kepatuhan UU 22/2009 Pasal 90
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Stat Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-6 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur-xl shadow-xl">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Batas Mengemudi Harian</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-extrabold text-slate-100">8 Jam</span>
                    <span class="text-xs text-slate-400">/ 480 Menit per Hari</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">UU 22/2009 Pasal 90 ayat (2) penugasan otomatis ditolak jika melampaui.</p>
            </div>

            <div class="p-6 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur-xl shadow-xl">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Istirahat Berkala</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-extrabold text-amber-400">30 Menit</span>
                    <span class="text-xs text-slate-400">setelah 4 Jam Berkendara</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">UU 22/2009 Pasal 90 ayat (3) istirahat wajib setelah 4 jam berturut-turut.</p>
            </div>

            <div class="p-6 rounded-2xl bg-slate-800/60 border border-slate-700/60 backdrop-blur-xl shadow-xl">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Standar Lisensi</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-extrabold text-blue-400">SIM B1 / B2</span>
                    <span class="text-xs text-slate-400">Umum</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Wajib aktif dan sesuai jenis armada (CDE/CDD/Fuso/Tronton).</p>
            </div>
        </div>

        {{-- Drivers Table --}}
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
            <div class="p-6 border-b border-slate-700/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-100">Daftar Pengemudi Aktif</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Total {{ $drivers->total() }} pengemudi terdaftar.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-6 py-3">Nama & No. Driver</th>
                            <th class="px-6 py-3">Kelas SIM</th>
                            <th class="px-6 py-3">Masa Berlaku SIM</th>
                            <th class="px-6 py-3">Hub Asal</th>
                            <th class="px-6 py-3">Jam Mengemudi Hari Ini (Maks 8 Jam)</th>
                            <th class="px-6 py-3">Mengemudi Beruntun (Maks 4 Jam)</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($drivers as $driver)
                            @php
                                $dailyPct = min(100, round(($driver->daily_driving_minutes / 480) * 100));
                                $contPct = min(100, round(($driver->continuous_driving_minutes / 240) * 100));
                                $isExpired = !$driver->isLicenseValid();
                            @endphp
                            <tr class="hover:bg-slate-700/20 transition-all">
                                <td class="px-6 py-3.5">
                                    <div class="font-bold text-slate-100">{{ $driver->user->name }}</div>
                                    <div class="text-[11px] font-mono text-slate-400">{{ $driver->driver_number }} • {{ $driver->user->phone ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="px-2 py-0.5 rounded font-bold font-mono text-[11px] bg-slate-700 text-amber-300">
                                        {{ $driver->license_class }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="{{ $isExpired ? 'text-rose-400 font-bold' : 'text-slate-200' }}">
                                        {{ $driver->license_expiry->format('d M Y') }}
                                    </div>
                                    @if($isExpired)
                                        <span class="text-[10px] text-rose-400 font-bold">KEDALUWARSA</span>
                                    @else
                                        <span class="text-[10px] text-emerald-400">Aktif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5 text-slate-300">
                                    {{ $driver->homeHub->name }}
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center justify-between text-[11px] mb-1">
                                        <span class="font-mono">{{ round($driver->daily_driving_minutes / 60, 1) }} / 8 jam</span>
                                        <span class="text-slate-400">{{ $dailyPct }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full {{ $dailyPct > 80 ? 'bg-rose-500' : ($dailyPct > 50 ? 'bg-amber-500' : 'bg-blue-500') }}" style="width: {{ $dailyPct }}%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center justify-between text-[11px] mb-1">
                                        <span class="font-mono">{{ round($driver->continuous_driving_minutes / 60, 1) }} / 4 jam</span>
                                        <span class="text-slate-400">{{ $contPct }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full {{ $contPct > 80 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $contPct }}%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
                                        @if($driver->status === 'available') bg-emerald-500/10 text-emerald-400 border border-emerald-500/30
                                        @elseif($driver->status === 'on_duty') bg-blue-500/10 text-blue-400 border border-blue-500/30
                                        @elseif($driver->status === 'resting') bg-amber-500/10 text-amber-400 border border-amber-500/30
                                        @else bg-rose-500/10 text-rose-400 border border-rose-500/30
                                        @endif
                                    ">
                                        {{ ucfirst($driver->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">Belum ada pengemudi terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($drivers->hasPages())
                <div class="p-4 border-t border-slate-700/50">
                    {{ $drivers->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
