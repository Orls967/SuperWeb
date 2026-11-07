@extends('layouts.app')

@section('content')
<div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 text-gray-300">
<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h2 class="text-xl font-bold text-white">BBM &amp; Anomali (M3 Regresi Baseline)</h2>
    @include('mining::partials.flash')
    <p class="my-3 text-sm">Dana ditahan Rp {{ number_format($totalHeldIdr) }} · Dipotong permanen Rp {{ number_format($totalDeductedIdr) }}
        · <a class="text-indigo-400" href="{{ route('mining.reports.export', ['type' => 'fuel']) }}">CSV kasus</a>
        · <a class="text-indigo-400" href="{{ route('mining.reports.export', ['type' => 'holds']) }}">CSV hold</a></p>

    <form method="GET" class="flex flex-wrap gap-2 mb-4">
        <input name="status" value="{{ request('status') }}" placeholder="status" class="rounded bg-gray-900 border-gray-600">
        <input name="contractor" value="{{ request('contractor') }}" placeholder="ID kontraktor (laporan)" class="rounded bg-gray-900 border-gray-600">
        <button class="px-3 py-1 bg-indigo-600 text-white rounded">Filter</button>
    </form>
    @if($summary)
        <p class="mb-4 text-sm">Kontraktor {{ $summary['contractor'] }}: {{ $summary['cases'] }} kasus, aktual {{ number_format($summary['actual_ml'] / 1000) }} L vs baseline {{ number_format($summary['baseline_ml'] / 1000) }} L ({{ number_format($summary['excess_bps'] / 100, 2) }}%), hold Rp {{ number_format($summary['held_idr']) }}.</p>
    @endif

    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">Kasus</th><th class="p-2">Tanggal</th><th class="p-2">Alat</th><th class="p-2">Aktual (L)</th><th class="p-2">Baseline (L)</th><th class="p-2">Lebih</th><th class="p-2">Status</th><th class="p-2">Resolusi</th></tr></thead>
        <tbody class="divide-y divide-gray-700">
        @forelse($cases as $c)
            <tr><td class="p-2 font-mono">{{ $c->case_number }}</td><td class="p-2">{{ $c->shift_date->format('Y-m-d') }} {{ $c->shift_code }}</td><td class="p-2">{{ $c->equipment?->equipment_code }}</td>
                <td class="p-2">{{ number_format($c->actual_fuel_ml / 1000) }}</td><td class="p-2">{{ number_format($c->baseline_fuel_ml / 1000) }}</td>
                <td class="p-2">{{ number_format($c->excess_bps / 100, 2) }}%</td><td class="p-2">{{ $c->status->value }}</td>
                <td class="p-2">
                    @if(in_array($c->status->value, ['payment_held', 'disputed'], true))
                        <form action="{{ route('mining.fuel.resolve', $c) }}" method="POST" class="flex gap-1">@csrf
                            <select name="action" class="rounded bg-gray-900 border-gray-600 text-xs"><option value="release">Rilis</option><option value="deduct">Potong</option></select>
                            <input name="notes" class="rounded bg-gray-900 border-gray-600 text-xs w-28" placeholder="catatan">
                            <button class="px-2 bg-indigo-600 text-white rounded text-xs">Proses</button>
                        </form>
                    @endif
                </td></tr>
        @empty
            <tr><td colspan="8" class="p-4 text-center text-gray-400">Tidak ada kasus.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $cases->links() }}
</div>

<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h3 class="text-lg font-semibold text-white mb-3">Pengisian solar dari tangki site</h3>
    <form action="{{ route('mining.fuel.dispense') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3">@csrf
        <select name="storage_id" required class="rounded bg-gray-900 border-gray-600">@foreach($storages as $s)<option value="{{ $s->id }}">{{ $s->storage_code }} ({{ number_format($s->current_stock_ml / 1000) }} L)</option>@endforeach</select>
        <select name="equipment_id" required class="rounded bg-gray-900 border-gray-600">@foreach($trucks as $t)<option value="{{ $t->id }}">{{ $t->equipment_code }}</option>@endforeach</select>
        <input type="number" name="volume_liters" min="1" required placeholder="Liter" class="rounded bg-gray-900 border-gray-600">
        <input type="number" name="unit_price_idr_per_liter" min="1" required placeholder="Harga Rp/L" class="rounded bg-gray-900 border-gray-600">
        <input name="flowmeter_start" required placeholder="Flowmeter awal" class="rounded bg-gray-900 border-gray-600">
        <input name="flowmeter_end" required placeholder="Flowmeter akhir" class="rounded bg-gray-900 border-gray-600">
        <button class="md:col-span-3 px-4 py-2 bg-indigo-600 text-white rounded">Catat pengisian</button>
    </form>
</div>
</div></div>
@endsection
