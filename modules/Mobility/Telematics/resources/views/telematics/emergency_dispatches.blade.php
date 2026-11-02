@extends('layouts.app')

@section('title', 'Telematics Emergency Breakdown Tow Dispatch')
@section('subtitle', 'Panggilan Derek Darurat Otomatis untuk Kendaraan Mogok / Grounded')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Form Order Derek Cepat</h3>
            <form action="{{ route('telematics.operations.dispatch-tow') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">ID Kendaraan</label>
                    <input type="number" name="vehicle_id" placeholder="misal: 101" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Lokasi Kejadian</label>
                    <input type="text" name="location" placeholder="misal: KM 42 Tol Jagorawi" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Penyebab Kerusakan</label>
                    <input type="text" name="reason" placeholder="misal: Overheating mesin & oli bocor" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tarif Standar (IDR Integer)</label>
                    <input type="number" name="fee_idr" value="750000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-sm">
                    Kirim Armada Derek Sekarang
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Kendaraan Ter-Grounding Saat Ini</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-4">Device ID</th>
                            <th class="p-4">Vehicle ID</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Heartbeat Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($groundedDevices as $d)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-4 font-mono font-bold text-slate-200">{{ $d->device_id }}</td>
                                <td class="p-4 font-mono text-violet-400">VEH-{{ $d->vehicle_id }}</td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-500/20 text-rose-400 uppercase">
                                        {{ $d->status }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-slate-400">{{ optional($d->last_heartbeat_at)->format('d M H:i:s') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500">Tidak ada kendaraan dalam status grounded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
