<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                {{ __('Manajemen Armada Multimoda (Fleet)') }}
            </h2>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30">
                    Sari Ranah Express Fleet
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{ tab: '{{ $activeTab }}' }">
        {{-- Navigation Tabs --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-700/60 pb-3">
            <button @click="tab = 'trucks'" :class="tab === 'trucks' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/25' : 'bg-slate-800 text-slate-400 hover:text-slate-200'" class="px-4 py-2 text-xs font-bold rounded-xl transition-all">
                Truk Darat (DA Plate)
            </button>
            <button @click="tab = 'trailers'" :class="tab === 'trailers' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/25' : 'bg-slate-800 text-slate-400 hover:text-slate-200'" class="px-4 py-2 text-xs font-bold rounded-xl transition-all">
                Trailer & Chassis
            </button>
            <button @click="tab = 'vessels'" :class="tab === 'vessels' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/25' : 'bg-slate-800 text-slate-400 hover:text-slate-200'" class="px-4 py-2 text-xs font-bold rounded-xl transition-all">
                Kapal Kargo (IMO Verified)
            </button>
            <button @click="tab = 'aircraft'" :class="tab === 'aircraft' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/25' : 'bg-slate-800 text-slate-400 hover:text-slate-200'" class="px-4 py-2 text-xs font-bold rounded-xl transition-all">
                Pesawat Freighter (PK-xxx)
            </button>
            <button @click="tab = 'containers'" :class="tab === 'containers' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/25' : 'bg-slate-800 text-slate-400 hover:text-slate-200'" class="px-4 py-2 text-xs font-bold rounded-xl transition-all">
                Kontainer (ISO 6346 Verified)
            </button>
            <button @click="tab = 'ulds'" :class="tab === 'ulds' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/25' : 'bg-slate-800 text-slate-400 hover:text-slate-200'" class="px-4 py-2 text-xs font-bold rounded-xl transition-all">
                Unit Load Devices (ULD)
            </button>
        </div>

        {{-- Trucks Tab --}}
        <div x-show="tab === 'trucks'" class="space-y-4">
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-100">Armada Truk Darat (Terhubung Vehicle Passport)</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Seluruh unit truk wajib berpaspor digital SHA-256 di Core Vehicles dengan plat nomor DA.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="px-6 py-3">Plat Nomor</th>
                                <th class="px-6 py-3">Jenis Truk</th>
                                <th class="px-6 py-3">Kapasitas (Payload/Vol)</th>
                                <th class="px-6 py-3">Syarat SIM</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Lokasi Terkini</th>
                                <th class="px-6 py-3">Paspor Digital</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($trucks as $truck)
                                <tr class="hover:bg-slate-700/20 transition-all">
                                    <td class="px-6 py-3.5 font-bold font-mono text-slate-100">{{ $truck->plate_number }}</td>
                                    <td class="px-6 py-3.5">{{ $truck->type->label() }}</td>
                                    <td class="px-6 py-3.5">{{ number_format($truck->payload_kg) }} kg / {{ number_format($truck->volume_dm3 / 1000, 1) }} m³</td>
                                    <td class="px-6 py-3.5 font-medium text-amber-300">{{ $truck->required_license }}</td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            {{ $truck->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-slate-400">{{ $truck->currentLocation?->name ?? 'Dalam Perjalanan' }}</td>
                                    <td class="px-6 py-3.5">
                                        @if($truck->vehicle)
                                            <a href="{{ route('passport.show', $truck->vehicle->uuid) }}" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 font-medium">
                                                <span>Paspor</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        @else
                                            <span class="text-rose-400 font-bold">Tanpa Paspor!</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">Belum ada armada truk terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Trailers Tab --}}
        <div x-show="tab === 'trailers'" class="space-y-4" x-cloak>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50">
                    <h3 class="text-base font-bold text-slate-100">Armada Trailer & Chassis Kontainer</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="px-6 py-3">Kode Trailer</th>
                                <th class="px-6 py-3">Tipe</th>
                                <th class="px-6 py-3">Kapasitas Muat</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Lokasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($trailers as $trl)
                                <tr>
                                    <td class="px-6 py-3.5 font-bold font-mono text-slate-100">{{ $trl->code }}</td>
                                    <td class="px-6 py-3.5">{{ $trl->type->label() }}</td>
                                    <td class="px-6 py-3.5">{{ number_format($trl->payload_kg) }} kg</td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            {{ $trl->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-slate-400">{{ $trl->currentLocation?->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada trailer terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Vessels Tab --}}
        <div x-show="tab === 'vessels'" class="space-y-4" x-cloak>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50">
                    <h3 class="text-base font-bold text-slate-100">Armada Kapal Kargo (Validasi Check Digit IMO)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="px-6 py-3">Nomor IMO</th>
                                <th class="px-6 py-3">Nama Kapal</th>
                                <th class="px-6 py-3">Bendera</th>
                                <th class="px-6 py-3">Kapasitas TEU</th>
                                <th class="px-6 py-3">Reefer Plugs</th>
                                <th class="px-6 py-3">DWT (Ton)</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($vessels as $ves)
                                <tr>
                                    <td class="px-6 py-3.5 font-bold font-mono text-cyan-300">IMO {{ $ves->imo_number }}</td>
                                    <td class="px-6 py-3.5 font-semibold text-slate-100">{{ $ves->name }}</td>
                                    <td class="px-6 py-3.5 uppercase font-mono">{{ $ves->flag }}</td>
                                    <td class="px-6 py-3.5">{{ number_format($ves->teu_capacity) }} TEU</td>
                                    <td class="px-6 py-3.5 text-blue-400">{{ $ves->reefer_plugs }} plugs</td>
                                    <td class="px-6 py-3.5">{{ number_format($ves->dwt_tonnes) }} ton</td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                                            {{ $ves->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">Belum ada kapal terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Aircraft Tab --}}
        <div x-show="tab === 'aircraft'" class="space-y-4" x-cloak>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50">
                    <h3 class="text-base font-bold text-slate-100">Armada Pesawat Freighter</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="px-6 py-3">Registrasi</th>
                                <th class="px-6 py-3">Tipe Pesawat</th>
                                <th class="px-6 py-3">Payload Maks</th>
                                <th class="px-6 py-3">Posisi ULD</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($aircraft as $air)
                                <tr>
                                    <td class="px-6 py-3.5 font-bold font-mono text-violet-300">{{ $air->registration }}</td>
                                    <td class="px-6 py-3.5 font-semibold text-slate-100">{{ $air->type }}</td>
                                    <td class="px-6 py-3.5">{{ number_format($air->max_payload_kg) }} kg</td>
                                    <td class="px-6 py-3.5 text-purple-400">{{ $air->uld_positions }} Posisi</td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-violet-500/10 text-violet-400 border border-violet-500/30">
                                            {{ $air->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada pesawat terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Containers Tab --}}
        <div x-show="tab === 'containers'" class="space-y-4" x-cloak>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50">
                    <h3 class="text-base font-bold text-slate-100">Armada Kontainer (Validasi Check Digit ISO 6346)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="px-6 py-3">Nomor Kontainer</th>
                                <th class="px-6 py-3">Size/Type ISO</th>
                                <th class="px-6 py-3">Tare (Kosong)</th>
                                <th class="px-6 py-3">Max Gross</th>
                                <th class="px-6 py-3">Payload Maks</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Lokasi Terkini</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($containers as $cnt)
                                <tr>
                                    <td class="px-6 py-3.5 font-bold font-mono text-emerald-300">{{ $cnt->container_number }}</td>
                                    <td class="px-6 py-3.5 font-mono text-slate-200">{{ $cnt->size_type }}</td>
                                    <td class="px-6 py-3.5">{{ number_format($cnt->tare_kg) }} kg</td>
                                    <td class="px-6 py-3.5">{{ number_format($cnt->max_gross_kg) }} kg</td>
                                    <td class="px-6 py-3.5 text-blue-400">{{ number_format($cnt->payloadCapacityKg()) }} kg</td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            {{ $cnt->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-slate-400">{{ $cnt->currentLocation?->name ?? 'In Transit' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">Belum ada kontainer terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ULDs Tab --}}
        <div x-show="tab === 'ulds'" class="space-y-4" x-cloak>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50">
                    <h3 class="text-base font-bold text-slate-100">Armada Unit Load Devices (ULD) Kargo Udara</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="px-6 py-3">Kode ULD</th>
                                <th class="px-6 py-3">Tipe</th>
                                <th class="px-6 py-3">Tare (Kosong)</th>
                                <th class="px-6 py-3">Max Gross</th>
                                <th class="px-6 py-3">Payload Maks</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($ulds as $uld)
                                <tr>
                                    <td class="px-6 py-3.5 font-bold font-mono text-purple-300">{{ $uld->uld_code }}</td>
                                    <td class="px-6 py-3.5 font-mono text-slate-200">{{ $uld->type }}</td>
                                    <td class="px-6 py-3.5">{{ number_format($uld->tare_kg) }} kg</td>
                                    <td class="px-6 py-3.5">{{ number_format($uld->max_gross_kg) }} kg</td>
                                    <td class="px-6 py-3.5 text-blue-400">{{ number_format($uld->payloadCapacityKg()) }} kg</td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/30">
                                            {{ $uld->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada ULD terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
