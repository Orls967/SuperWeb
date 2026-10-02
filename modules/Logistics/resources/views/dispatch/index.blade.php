<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Papan Dispatch</h2>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                Validasi SIM &bull; UU 22/2009 Pasal 90 &bull; Paspor Kendaraan
            </span>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Trip darat --}}
        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl shadow-xl overflow-hidden">
            <div class="p-5 border-b border-slate-700/50">
                <h3 class="text-base font-bold text-slate-100">Trip Darat Menunggu Keberangkatan</h3>
                <p class="text-xs text-slate-400 mt-0.5">Pilih truk dan pengemudi; sistem menolak SIM kedaluwarsa/tidak sesuai, jam kerja berlebih, truk maintenance, tabrakan jadwal, dan paspor rusak.</p>
            </div>
            <div class="divide-y divide-slate-700/40">
                @forelse($schedules as $schedule)
                    <div class="p-5 space-y-3" data-schedule="{{ $schedule->schedule_number }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="font-mono text-amber-300 font-semibold">{{ $schedule->schedule_number }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ $schedule->origin?->name }} &rarr; {{ $schedule->destination?->name }}
                                    &bull; ETD {{ $schedule->etd->format('d/m H:i') }} &bull; ETA {{ $schedule->eta->format('d/m H:i') }}
                                </div>
                            </div>
                            @if($schedule->asset_id)
                                <span class="px-2 py-1 rounded-lg text-[11px] bg-emerald-900/40 text-emerald-300 border border-emerald-700">
                                    {{ $schedule->asset?->plate_number }} &bull; {{ $schedule->driver?->user?->name }}
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-lg text-[11px] bg-rose-900/40 text-rose-300 border border-rose-700">Belum ditugaskan</span>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('logistics.dispatch.assign') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2">
                            @csrf
                            <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                            <select name="truck_id" required class="bg-slate-900 border-slate-700 text-slate-200 text-xs rounded-lg">
                                <option value="">Pilih truk...</option>
                                @foreach($trucks as $truck)
                                    <option value="{{ $truck->id }}" @selected((int) $schedule->asset_id === $truck->id)>{{ $truck->plate_number }} ({{ $truck->type->value }} &bull; {{ $truck->required_license }})</option>
                                @endforeach
                            </select>
                            <select name="driver_id" required class="bg-slate-900 border-slate-700 text-slate-200 text-xs rounded-lg">
                                <option value="">Pilih pengemudi...</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" @selected((int) $schedule->driver_id === $driver->id)>
                                        {{ $driver->driver_number }} &ndash; {{ $driver->user?->name }} ({{ $driver->license_class }}{{ $driver->isLicenseValid() ? '' : ', SIM EXPIRED' }})
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg bg-blue-600 hover:bg-blue-500 text-white">
                                {{ $schedule->asset_id ? 'Ganti Penugasan' : 'Tugaskan' }}
                            </button>
                        </form>

                        @if($schedule->asset_id)
                            <form method="POST" action="{{ route('logistics.dispatch.release', $schedule) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-rose-300 hover:text-rose-200 underline">Lepas penugasan</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Tidak ada trip darat yang menunggu penugasan.</div>
                @endforelse
            </div>
        </section>

        {{-- Last-mile / pickup --}}
        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl shadow-xl overflow-hidden">
            <div class="p-5 border-b border-slate-700/50">
                <h3 class="text-base font-bold text-slate-100">Tugas Pickup &amp; Pengantaran Last-Mile</h3>
                <p class="text-xs text-slate-400 mt-0.5">Resi berstatus Dipesan (pickup) atau Tiba di Hub (pengantaran) ditugaskan ke pengemudi.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px] tracking-wider">
                        <tr><th class="px-4 py-3">Resi</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Rute</th><th class="px-4 py-3">Pengemudi</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($pendingShipments as $shipment)
                            <tr>
                                <td class="px-4 py-3 font-mono text-amber-300">{{ $shipment->tracking_number }}</td>
                                <td class="px-4 py-3">{{ $shipment->status->label() }}</td>
                                <td class="px-4 py-3">{{ $shipment->origin?->city }} &rarr; {{ $shipment->destination?->city }}</td>
                                <td class="px-4 py-3">{{ $shipment->driver?->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('logistics.dispatch.assign-shipment') }}" class="flex gap-2">
                                        @csrf
                                        <input type="hidden" name="shipment_id" value="{{ $shipment->id }}">
                                        <select name="driver_id" required class="bg-slate-900 border-slate-700 text-slate-200 text-xs rounded-lg">
                                            <option value="">Pengemudi...</option>
                                            @foreach($drivers as $driver)
                                                <option value="{{ $driver->id }}" @selected((int) $shipment->driver_id === $driver->id)>{{ $driver->driver_number }} &ndash; {{ $driver->user?->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-blue-600 hover:bg-blue-500 text-white">Tugaskan</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Tidak ada resi yang menunggu penugasan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Riwayat --}}
        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl shadow-xl overflow-hidden">
            <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Riwayat Penugasan Terakhir</h3></div>
            <ul class="divide-y divide-slate-700/40 text-xs text-slate-300">
                @forelse($recentAssignments as $a)
                    <li class="px-5 py-3 flex flex-wrap justify-between gap-2">
                        <span><span class="font-mono text-amber-300">{{ $a->schedule?->schedule_number }}</span> &bull; {{ $a->truck?->plate_number }} &bull; {{ $a->driver?->user?->name }}</span>
                        <span class="text-slate-400">{{ $a->status }} &bull; oleh {{ $a->assigner?->name ?? 'sistem' }} &bull; {{ $a->assigned_at?->format('d/m H:i') }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-400">Belum ada riwayat.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-app-layout>
