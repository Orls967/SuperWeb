@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Simulator Optimasi Overbooking H1</h1>
        <p class="text-sm text-slate-500">Kurva optimasi ekspektasi nilai kamar terjual versus risiko biaya walk.</p>
    </div>

    <!-- Parameter Simulator Form -->
    <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('hotel.rates.simulator') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-700">Kapasitas Kamar</label>
                <input type="number" name="capacity" value="{{ $capacity }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">Pendapatan / Kamar (Rp)</label>
                <input type="number" name="revenue" value="{{ $revenue }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">Biaya Walk / Kamar (Rp)</label>
                <input type="number" name="walk_cost" value="{{ $walkCost }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">
                    Simulasikan Kurva H1
                </button>
            </div>
        </form>
    </div>

    <!-- Hasil Optimasi -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500">Batas Overbooking Optimal (k)</dt>
            <dd class="mt-2 text-3xl font-extrabold text-sky-600 tabular-nums">+{{ $res['optimal_k'] }} Kamar</dd>
            <p class="mt-1 text-xs text-slate-400">Plafon Kebijakan: {{ $res['policy_ceiling_k'] }} kamar (3%)</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500">Ekspektasi Nilai Bersih</dt>
            <dd class="mt-2 text-3xl font-extrabold text-emerald-600 tabular-nums">Rp {{ number_format($res['expected_net_value_idr'], 0, ',', '.') }}</dd>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500">Ekspektasi Tamu Ter-Walk</dt>
            <dd class="mt-2 text-3xl font-extrabold text-amber-600 tabular-nums">{{ number_format($res['expected_walk_rooms'], 2) }} Tamu</dd>
        </div>
    </div>

    <!-- Tabel Kurva Nilai per k -->
    <div class="bg-white shadow-sm rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b border-slate-100 font-semibold text-slate-900">
            Tabel Kurva Ekspektasi Nilai per Tambahan Kamar (k = 0..6)
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th class="py-3 px-4 text-center font-semibold">k (Kamar Overbook)</th>
                    <th class="py-3 px-4 text-right font-semibold">E[Nilai Bersih]</th>
                    <th class="py-3 px-4 text-center font-semibold">Status Keputusan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach($res['values_by_k'] as $k => $val)
                    <tr class="{{ $k === $res['optimal_k'] ? 'bg-emerald-50/50 font-bold' : '' }}">
                        <td class="py-3 px-4 text-center font-mono">k = {{ $k }}</td>
                        <td class="py-3 px-4 text-right tabular-nums">Rp {{ number_format($val, 0, ',', '.') }}</td>
                        <td class="py-3 px-4 text-center">
                            @if($k === $res['optimal_k'])
                                <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs">OPTIMAL TERPILIH</span>
                            @elseif($k > $res['policy_ceiling_k'])
                                <span class="rounded bg-rose-100 text-rose-800 px-2 py-0.5 text-xs">MELEBIHI PLAFON 3%</span>
                            @else
                                <span class="text-xs text-slate-400">Sub-optimal</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
