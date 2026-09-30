<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                {{ __('Jalur Transportasi Multimoda (Lanes)') }}
            </h2>
            <a href="{{ route('logistics.locations.index') }}" class="px-4 py-2 text-sm font-medium rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 transition-all">
                ← Kembali ke Peta Jaringan
            </a>
        </div>
    </x-slot>

    <div class="py-8 space-y-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Flash Notification --}}
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form Tambah Jalur --}}
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl p-6">
            <h3 class="text-base font-bold text-slate-100 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Jalur Baru Antar Titik
            </h3>

            <form method="POST" action="{{ route('logistics.lanes.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                @csrf

                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Asal (Origin)</label>
                    <select name="origin_id" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 focus:ring-1 focus:ring-blue-500 focus:outline-none">
                        <option value="">Pilih Asal...</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('origin_id') == $loc->id ? 'selected' : '' }}>
                                [{{ $loc->code }}] {{ $loc->name }} ({{ $loc->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Tujuan (Destination)</label>
                    <select name="destination_id" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 focus:ring-1 focus:ring-blue-500 focus:outline-none">
                        <option value="">Pilih Tujuan...</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('destination_id') == $loc->id ? 'selected' : '' }}>
                                [{{ $loc->code }}] {{ $loc->name }} ({{ $loc->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Moda</label>
                    <select name="mode" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 focus:ring-1 focus:ring-blue-500 focus:outline-none">
                        @foreach($modes as $mode)
                            <option value="{{ $mode->value }}" {{ old('mode') === $mode->value ? 'selected' : '' }}>
                                {{ $mode->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Jarak (KM)</label>
                    <input type="number" step="0.1" name="distance_km" value="{{ old('distance_km', 50) }}" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Transit Std (Menit)</label>
                    <input type="number" name="standard_transit_minutes" value="{{ old('standard_transit_minutes', 60) }}" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                </div>

                <div class="lg:col-span-5"></div>

                <div class="lg:col-span-1">
                    <button type="submit" class="w-full px-4 py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-500/25 transition-all">
                        Simpan Jalur
                    </button>
                </div>
            </form>
        </div>

        {{-- Tabel Daftar Jalur --}}
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
            <div class="p-6 border-b border-slate-700/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-100">Daftar Jalur Transportasi</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Total {{ $lanes->total() }} jalur aktif menghubungkan simpul logistik.</p>
                </div>

                {{-- Filter Bar --}}
                <form method="GET" action="{{ route('logistics.lanes.index') }}" class="flex items-center gap-3">
                    <select name="mode" class="px-3 py-1.5 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Semua Moda</option>
                        @foreach($modes as $mode)
                            <option value="{{ $mode->value }}" {{ ($filters['mode'] ?? '') === $mode->value ? 'selected' : '' }}>
                                {{ $mode->label() }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 transition-all">
                        Filter
                    </button>
                    @if(!empty($filters['mode']))
                        <a href="{{ route('logistics.lanes.index') }}" class="px-3 py-1.5 text-xs text-slate-400 hover:text-slate-200">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-6 py-3">Moda</th>
                            <th class="px-6 py-3">Asal (Origin)</th>
                            <th class="px-6 py-3">Tujuan (Destination)</th>
                            <th class="px-6 py-3">Jarak</th>
                            <th class="px-6 py-3">Estimasi Waktu Standar</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($lanes as $lane)
                            <tr class="hover:bg-slate-700/20 transition-all">
                                <td class="px-6 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border
                                        @if($lane->mode->value === 'road') bg-amber-500/10 text-amber-400 border-amber-500/30
                                        @elseif($lane->mode->value === 'sea') bg-cyan-500/10 text-cyan-400 border-cyan-500/30
                                        @else bg-purple-500/10 text-purple-400 border-purple-500/30
                                        @endif
                                    ">
                                        {{ $lane->mode->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="font-bold text-slate-100">{{ $lane->origin->name }}</div>
                                    <div class="text-[11px] font-mono text-slate-400">{{ $lane->origin->code }} • {{ $lane->origin->city }}</div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="font-bold text-slate-100">{{ $lane->destination->name }}</div>
                                    <div class="text-[11px] font-mono text-slate-400">{{ $lane->destination->code }} • {{ $lane->destination->city }}</div>
                                </td>
                                <td class="px-6 py-3.5 font-mono text-slate-200">
                                    {{ number_format($lane->distanceKm(), 1) }} km
                                </td>
                                <td class="px-6 py-3.5 text-slate-300">
                                    {{ $lane->standardTransitHours() }} jam ({{ $lane->standard_transit_minutes }} mnt)
                                </td>
                                <td class="px-6 py-3.5 text-right">
                                    <form method="POST" action="{{ route('logistics.lanes.destroy', $lane) }}" class="inline" onsubmit="return confirm('Hapus jalur ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-400 hover:text-rose-300 font-medium">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                    Tidak ada jalur ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($lanes->hasPages())
                <div class="p-4 border-t border-slate-700/50">
                    {{ $lanes->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
