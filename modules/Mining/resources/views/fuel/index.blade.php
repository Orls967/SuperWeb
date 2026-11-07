@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">Fuel Anomaly Detection (M3 Linear Baseline Regression)</h2>
                    <p class="text-sm text-gray-400">Deteksi deviasi konsumsi solar per ton-km dan escrow pemotongan/penahanan tagihan kontraktor.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Tabel Kasus Anomali BBM -->
            <div class="bg-gray-700/40 rounded-lg p-6 border border-gray-600">
                <h3 class="text-lg font-semibold text-white mb-4">Kasus Deviasi Konsumsi BBM & Status Hold</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-800 text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="p-3">Case Code</th>
                                <th class="p-3">Tanggal Shift</th>
                                <th class="p-3">Alat</th>
                                <th class="p-3">Aktual (L)</th>
                                <th class="p-3">Baseline (L)</th>
                                <th class="p-3">Deviasi %</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Resolusi Hold</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($cases as $case)
                                <tr class="hover:bg-gray-750">
                                    <td class="p-3 font-mono font-medium text-rose-400">{{ $case->case_code }}</td>
                                    <td class="p-3">{{ $case->shift_date?->format('Y-m-d') }} ({{ $case->shift }})</td>
                                    <td class="p-3">{{ $case->equipment?->code ?? '-' }}</td>
                                    <td class="p-3">{{ number_format($case->actual_liters, 1) }} L</td>
                                    <td class="p-3">{{ number_format($case->expected_baseline_liters, 1) }} L</td>
                                    <td class="p-3 font-mono font-bold text-rose-300">
                                        +{{ number_format((float)$case->deviation_percentage, 1) }}%
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-rose-900/60 text-rose-300 border border-rose-600">
                                            {{ $case->status->value }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        @if(in_array($case->status->value, ['OPEN', 'INVESTIGATING', 'PAYMENT_HELD']))
                                            <form action="{{ route('mining.fuel.resolve', $case) }}" method="POST" class="inline-flex items-center space-x-2">
                                                @csrf
                                                <select name="action" class="text-xs rounded bg-gray-900 border-gray-600 text-white p-1">
                                                    <option value="RELEASE">Release (Bebaskan)</option>
                                                    <option value="DEDUCT">Deduct (Potong Tagihan)</option>
                                                </select>
                                                <input type="text" name="resolution_notes" placeholder="Catatan resolusi" required class="text-xs rounded bg-gray-900 border-gray-600 text-white p-1 w-32">
                                                <button type="submit" class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs rounded">
                                                    Proses
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400">Kasus Selesai</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-gray-400">Tidak ada kasus anomali bahan bakar yang terdeteksi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $cases->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
