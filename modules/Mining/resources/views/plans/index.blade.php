@extends('layouts.app')

@section('content')
<div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg text-gray-300">
    <h2 class="text-xl font-bold text-white">Perencanaan Tambang (M1 Weighted Time Phasing)</h2>
    @include('mining::partials.flash')

    <form action="{{ route('mining.plans.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 my-6">
        @csrf
        <select name="site_id" required class="rounded bg-gray-900 border-gray-600">
            @foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->site_code }} — {{ $site->name }}</option>@endforeach
        </select>
        <select name="pit_id" required class="rounded bg-gray-900 border-gray-600">
            @foreach($pits as $pit)<option value="{{ $pit->id }}">{{ $pit->pit_code }} — {{ $pit->name }}</option>@endforeach
        </select>
        <input name="period_month" value="{{ $month }}" pattern="\d{4}-\d{2}" required class="rounded bg-gray-900 border-gray-600" placeholder="YYYY-MM">
        <input name="commodity" value="NICKEL" required class="rounded bg-gray-900 border-gray-600">
        <input type="number" name="target_tonnage_kg" min="1000" required class="rounded bg-gray-900 border-gray-600" placeholder="Target tonase (kg)">
        <input type="number" name="target_grade_ppm" min="0" required class="rounded bg-gray-900 border-gray-600" placeholder="Target kadar (ppm)">
        <button class="md:col-span-3 px-4 py-2 bg-indigo-600 text-white rounded">Susun &amp; fase-kan ke shift</button>
    </form>

    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">Kode</th><th class="p-2">Site</th><th class="p-2">Periode</th><th class="p-2">Ore (ton)</th><th class="p-2">Versi</th><th class="p-2">Status</th><th class="p-2">Shift</th><th class="p-2">Re-plan</th></tr></thead>
        <tbody class="divide-y divide-gray-700">
        @forelse($plans as $plan)
            <tr>
                <td class="p-2 font-mono">{{ $plan->plan_code }}</td>
                <td class="p-2">{{ $plan->site?->site_code }}</td>
                <td class="p-2">{{ $plan->period_month }}</td>
                <td class="p-2">{{ number_format($plan->target_ore_kg / 1000) }}</td>
                <td class="p-2">{{ $plan->version }}</td>
                <td class="p-2">{{ $plan->status->value }}</td>
                <td class="p-2">{{ $plan->shifts_count }}</td>
                <td class="p-2">
                    <form action="{{ route('mining.plans.replan', $plan) }}" method="POST" class="flex gap-2">@csrf
                        <input type="number" name="additional_rain_hours" min="0" max="12" class="w-20 rounded bg-gray-900 border-gray-600 text-xs" placeholder="jam hujan">
                        <button class="px-2 bg-amber-600 text-white rounded text-xs">Hitung ulang</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="p-4 text-center text-gray-400">Belum ada rencana.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $plans->links() }}
</div>

<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg text-gray-300">
    <h3 class="text-lg font-semibold text-white mb-3">Kurva-S rencana vs aktual</h3>
    <form method="GET" class="flex flex-wrap gap-2 mb-4">
        <select name="pit_id" class="rounded bg-gray-900 border-gray-600">
            @foreach($pits as $pit)<option value="{{ $pit->id }}" @selected($pit->id === $pitId)>{{ $pit->pit_code }}</option>@endforeach
        </select>
        <input name="month" value="{{ $month }}" class="rounded bg-gray-900 border-gray-600">
        <button class="px-3 py-1 bg-indigo-600 text-white rounded">Tampilkan</button>
        <a href="{{ route('mining.reports.export', ['type' => 'operations']) }}" class="px-3 py-1 bg-gray-600 text-white rounded">CSV</a>
    </form>
    @if($curve)
        <p class="mb-2">SPI {{ number_format($curve['spi_bps'] / 100, 2) }}% — {{ $curve['status'] }}</p>
        <div class="overflow-x-auto"><table class="w-full text-sm">
            <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">Tanggal</th><th class="p-2">Rencana kum. (ton)</th><th class="p-2">Aktual kum. (ton)</th><th class="p-2">Selisih (ton)</th></tr></thead>
            <tbody>@foreach($curve['points'] as $pt)
                <tr><td class="p-2">{{ $pt['date'] }}</td><td class="p-2">{{ number_format($pt['planned_cum_kg'] / 1000) }}</td><td class="p-2">{{ number_format($pt['actual_cum_kg'] / 1000) }}</td><td class="p-2">{{ number_format($pt['variance_kg'] / 1000) }}</td></tr>
            @endforeach</tbody>
        </table></div>
    @endif
</div>
</div></div>
@endsection
