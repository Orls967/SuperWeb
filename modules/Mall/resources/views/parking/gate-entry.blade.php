<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">Gate Masuk Parkir</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Terbitkan tiket masuk, deteksi plat member otomatis, dan pantau kapasitas tiap zona
                </p>
            </div>
            <a href="{{ route('mall.parking.gate.exit', ['property_id' => $property?->id]) }}"
               class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-md transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Pindah ke Gate Keluar</span>
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="parkingGate({
            occupancyUrl: '{{ route('mall.parking.occupancy', ['property_id' => $property?->id]) }}',
            totalCapacity: {{ (int) $zones->sum('total_capacity') }},
            totalOccupied: {{ (int) $zones->sum('current_occupancy') }}
        })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Form penerbitan tiket --}}
                <div class="lg:col-span-5 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="font-bold text-slate-800 mb-4">Terbitkan Tiket Masuk</h3>

                    <form action="{{ route('mall.parking.gate.check-in') }}" method="POST" class="space-y-4">
                        @csrf

                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Properti</label>
                            <select name="property_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach($properties as $p)
                                    <option value="{{ $p->id }}" @selected($property?->id === $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            @error('property_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Plat Nomor</label>
                            <input type="text" name="plate_number" value="{{ old('plate_number') }}" required
                                   placeholder="DA 1234 XY"
                                   class="w-full rounded-xl border-slate-300 text-lg font-mono font-bold uppercase tracking-wider focus:border-blue-500 focus:ring-blue-500">
                            @error('plate_number')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                            <p class="text-[11px] text-slate-400 mt-1">
                                Plat yang terdaftar sebagai member atau ada di My Garage akan dicocokkan otomatis.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1.5">Jenis Kendaraan</label>
                                <select name="vehicle_type" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach($vehicleTypes as $type)
                                        <option value="{{ $type->value }}" @selected(old('vehicle_type') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('vehicle_type')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1.5">Gate</label>
                                <input type="text" name="entry_gate" value="{{ old('entry_gate', 'Gate Masuk 1') }}"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('entry_gate')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Zona (opsional)</label>
                            <select name="parking_zone_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih otomatis zona yang masih tersedia</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}" @disabled($zone->isFull())>
                                        {{ $zone->code }} — {{ $zone->name }}
                                        ({{ $zone->availableSlots() }} slot tersisa){{ $zone->isFull() ? ' — PENUH' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parking_zone_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit"
                                class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-sm font-bold shadow-md shadow-blue-500/20 transition-all">
                            Cetak Tiket & Buka Palang
                        </button>
                    </form>

                    <div class="mt-6 pt-5 border-t border-slate-200">
                        <h4 class="font-bold text-slate-800 text-sm mb-3">Tiket Hilang</h4>
                        <form action="{{ route('mall.parking.gate.lost-ticket') }}" method="POST" class="flex gap-2">
                            @csrf
                            <input type="hidden" name="property_id" value="{{ $property?->id }}">
                            <input type="text" name="plate_number" required placeholder="Plat nomor kendaraan"
                                   class="flex-1 rounded-xl border-slate-300 text-sm font-mono uppercase focus:border-amber-500 focus:ring-amber-500">
                            <button type="submit"
                                    class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold whitespace-nowrap transition-all">
                                Proses Denda
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Okupansi real-time --}}
                <div class="lg:col-span-7 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-slate-800">Okupansi Zona</h3>
                            <span class="text-[11px] text-slate-400" x-text="updatedAt ? 'Diperbarui ' + updatedAt : 'Memuat…'"></span>
                        </div>

                        <div class="flex items-baseline gap-3 mb-4">
                            <span class="text-3xl font-black text-slate-800" x-text="occupancyRate + '%'"></span>
                            <span class="text-sm text-slate-500">
                                <span x-text="totalOccupied"></span> / <span x-text="totalCapacity"></span> slot terpakai
                            </span>
                        </div>

                        <div class="space-y-3">
                            <template x-for="zone in zones" :key="zone.id">
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-bold text-slate-700" x-text="zone.code + ' — ' + zone.name"></span>
                                        <span class="font-mono text-slate-500">
                                            <span x-text="zone.occupied"></span>/<span x-text="zone.capacity"></span>
                                            <span x-show="zone.is_full" class="ml-2 text-rose-600 font-bold">PENUH</span>
                                        </span>
                                    </div>
                                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full transition-all" :class="zoneBarClass(zone)" :style="`width: ${Math.min(100, zone.rate)}%`"></div>
                                    </div>
                                </div>
                            </template>

                            <p x-show="zones.length === 0" class="text-sm text-slate-400 py-4 text-center">
                                Belum ada zona parkir aktif pada properti ini.
                            </p>
                        </div>
                    </div>

                    {{-- Kendaraan yang sedang di dalam --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200">
                            <h3 class="font-bold text-slate-800">Kendaraan Terakhir Masuk</h3>
                        </div>

                        @if($recentSessions->isEmpty())
                            <p class="px-6 py-8 text-sm text-slate-400 text-center">Belum ada kendaraan di area parkir.</p>
                        @else
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="text-left px-6 py-3 font-bold">Tiket</th>
                                        <th class="text-left px-6 py-3 font-bold">Plat</th>
                                        <th class="text-left px-6 py-3 font-bold">Zona</th>
                                        <th class="text-right px-6 py-3 font-bold">Masuk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($recentSessions as $session)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-6 py-3 font-mono text-slate-700">{{ $session->ticket_number }}</td>
                                            <td class="px-6 py-3 font-mono font-bold text-slate-800">{{ $session->plate_number }}</td>
                                            <td class="px-6 py-3 text-slate-500">{{ $session->zone?->code }}</td>
                                            <td class="px-6 py-3 text-right text-slate-500">{{ $session->entry_time->format('H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
