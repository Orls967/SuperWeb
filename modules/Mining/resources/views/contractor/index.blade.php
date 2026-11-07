@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">Contractor Mining Hauling & Fuel Dispute Portal</h2>
                    <p class="text-sm text-gray-400">Portal mandiri kontraktor untuk monitoring siklus hauling, hold tagihan BBM, dan pengajuan sanggahan (dispute).</p>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Ringkasan Hold Tagihan Kontraktor -->
            <div class="bg-gray-700/40 rounded-lg p-6 border border-gray-600 mb-6">
                <h3 class="text-lg font-semibold text-white mb-4">Daftar Hold Tagihan (Escrow / Deductions)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-800 text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="p-3">Hold Code</th>
                                <th class="p-3">Ref Anomali</th>
                                <th class="p-3">Jumlah Ditahan (IDR)</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Tanggal Dibuat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($myHolds as $hold)
                                <tr class="hover:bg-gray-750">
                                    <td class="p-3 font-mono font-medium text-amber-400">{{ $hold->hold_code }}</td>
                                    <td class="p-3">{{ $hold->fuel_anomaly_case_id }}</td>
                                    <td class="p-3 font-mono">Rp {{ number_format($hold->held_amount_idr, 0, ',', '.') }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-amber-900/60 text-amber-300 border border-amber-600">
                                            {{ $hold->status }}
                                        </span>
                                    </td>
                                    <td class="p-3">{{ $hold->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-4 text-center text-gray-400">Tidak ada penahanan tagihan aktif.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kasus Anomali BBM & Form Sanggahan -->
            <div class="bg-gray-700/40 rounded-lg p-6 border border-gray-600">
                <h3 class="text-lg font-semibold text-white mb-4">Kasus Anomali BBM Kontraktor</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-800 text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="p-3">Case Code</th>
                                <th class="p-3">Tanggal</th>
                                <th class="p-3">Alat</th>
                                <th class="p-3">Aktual vs Baseline</th>
                                <th class="p-3">Deviasi %</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Ajukan Sanggahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($myCases as $c)
                                <tr class="hover:bg-gray-750">
                                    <td class="p-3 font-mono font-medium text-rose-400">{{ $c->case_code }}</td>
                                    <td class="p-3">{{ $c->shift_date?->format('Y-m-d') }}</td>
                                    <td class="p-3">{{ $c->equipment?->code ?? '-' }}</td>
                                    <td class="p-3">{{ number_format($c->actual_liters, 1) }} L / {{ number_format($c->expected_baseline_liters, 1) }} L</td>
                                    <td class="p-3 font-bold text-rose-300">+{{ number_format((float)$c->deviation_percentage, 1) }}%</td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-rose-900/60 text-rose-300 border border-rose-600">
                                            {{ $c->status->value }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <form action="{{ route('mining.contractor.dispute') }}" method="POST" class="inline-flex items-center space-x-2">
                                            @csrf
                                            <input type="hidden" name="case_id" value="{{ $c->id }}">
                                            <input type="text" name="reason" placeholder="Alasan sanggahan..." required class="text-xs rounded bg-gray-900 border-gray-600 text-white p-1 w-40">
                                            <button type="submit" class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white text-xs rounded font-medium">
                                                Sanggah
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-gray-400">Tidak ada kasus anomali untuk armada Anda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
