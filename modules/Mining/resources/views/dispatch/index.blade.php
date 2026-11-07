@extends('layouts.app')

@section('content')
<div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 text-gray-300">
<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h2 class="text-xl font-bold text-white">Papan Dispatch Shift (M2 Need-Time)</h2>
    @include('mining::partials.flash')

    <form method="GET" class="flex flex-wrap gap-2 my-4">
        <input type="date" name="shift_date" value="{{ $shiftDate }}" class="rounded bg-gray-900 border-gray-600">
        <input name="shift_code" value="{{ $shiftCode }}" class="rounded bg-gray-900 border-gray-600">
        <button class="px-3 py-1 bg-indigo-600 text-white rounded">Muat</button>
        <a href="{{ route('mining.reports.export', ['type' => 'haul']) }}" class="px-3 py-1 bg-gray-600 text-white rounded">CSV</a>
    </form>

    <form action="{{ route('mining.dispatch.assign') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        @csrf
        <select name="site_id" required class="rounded bg-gray-900 border-gray-600">@foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->site_code }}</option>@endforeach</select>
        <select name="pit_id" required class="rounded bg-gray-900 border-gray-600">@foreach($pits as $p)<option value="{{ $p->id }}">{{ $p->pit_code }}</option>@endforeach</select>
        <select name="truck_id" required class="rounded bg-gray-900 border-gray-600">@foreach($trucks as $t)<option value="{{ $t->id }}">{{ $t->equipment_code }}</option>@endforeach</select>
        <input type="hidden" name="shift_date" value="{{ $shiftDate }}"><input type="hidden" name="shift_code" value="{{ $shiftCode }}">
        <input type="number" name="target_payload_kg" min="1000" required class="rounded bg-gray-900 border-gray-600" placeholder="Target payload (kg)">
        <button class="md:col-span-2 px-4 py-2 bg-indigo-600 text-white rounded">Tugaskan otomatis (M2)</button>
    </form>

    <details class="mb-4"><summary class="cursor-pointer text-amber-400">Override dispatcher (wajib alasan)</summary>
        <form action="{{ route('mining.dispatch.override') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
            @csrf
            <select name="site_id" required class="rounded bg-gray-900 border-gray-600">@foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->site_code }}</option>@endforeach</select>
            <select name="pit_id" required class="rounded bg-gray-900 border-gray-600">@foreach($pits as $p)<option value="{{ $p->id }}">{{ $p->pit_code }}</option>@endforeach</select>
            <select name="truck_id" required class="rounded bg-gray-900 border-gray-600">@foreach($trucks as $t)<option value="{{ $t->id }}">{{ $t->equipment_code }}</option>@endforeach</select>
            <select name="chosen_shovel_id" required class="rounded bg-gray-900 border-gray-600">@foreach($shovels as $s)<option value="{{ $s->id }}">{{ $s->equipment_code }}</option>@endforeach</select>
            <input type="hidden" name="shift_date" value="{{ $shiftDate }}"><input type="hidden" name="shift_code" value="{{ $shiftCode }}">
            <input type="number" name="target_payload_kg" min="1000" required class="rounded bg-gray-900 border-gray-600" placeholder="Target payload (kg)">
            <input name="override_reason" minlength="5" required class="md:col-span-2 rounded bg-gray-900 border-gray-600" placeholder="Alasan override">
            <button class="px-4 py-2 bg-amber-600 text-white rounded">Override</button>
        </form>
    </details>

    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">Truk</th><th class="p-2">Shovel</th><th class="p-2">Seq</th><th class="p-2">Status</th><th class="p-2">Target (kg)</th><th class="p-2">Aktual (kg)</th></tr></thead>
        <tbody class="divide-y divide-gray-700">
        @forelse($cycles as $c)
            <tr><td class="p-2 font-mono">{{ $c->truck?->equipment_code }}</td><td class="p-2">{{ $c->shovel?->equipment_code }}</td><td class="p-2">{{ $c->cycle_seq }}</td><td class="p-2">{{ $c->status->value }}</td><td class="p-2">{{ number_format($c->target_payload_kg) }}</td><td class="p-2">{{ number_format($c->actual_payload_kg) }}</td></tr>
        @empty
            <tr><td colspan="6" class="p-4 text-center text-gray-400">Tidak ada siklus pada shift ini.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $cycles->links() }}
</div>

<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h3 class="text-lg font-semibold text-white mb-1">Gantt siklus (dari riwayat transisi)</h3>
    <p class="text-sm mb-3">Truk menunggu {{ $gantt['truck_wait_minutes'] }} mnt · Shovel idle {{ $gantt['shovel_idle_minutes'] }} mnt</p>
    @foreach($gantt['rows'] as $row)
        <div class="mb-2 text-xs"><span class="font-mono">{{ $row['truck'] }}→{{ $row['shovel'] }} #{{ $row['cycle_seq'] }}</span>
            @foreach($row['intervals'] as $iv)<span class="inline-block px-2 py-0.5 mr-1 rounded bg-gray-700">{{ $iv['stage'] }} {{ $iv['minutes'] }}m</span>@endforeach
        </div>
    @endforeach
</div>

<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h3 class="text-lg font-semibold text-white mb-3">Simulator Match Factor</h3>
    <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-3">
        <input type="hidden" name="shift_date" value="{{ $shiftDate }}"><input type="hidden" name="shift_code" value="{{ $shiftCode }}">
        <input type="number" name="sim_trucks" value="{{ request('sim_trucks', 12) }}" class="rounded bg-gray-900 border-gray-600" title="Truk">
        <input type="number" name="sim_shovels" value="{{ request('sim_shovels', 3) }}" class="rounded bg-gray-900 border-gray-600" title="Shovel">
        <input type="number" name="sim_loading" value="{{ request('sim_loading', 4) }}" class="rounded bg-gray-900 border-gray-600" title="Menit muat">
        <input type="number" name="sim_cycle" value="{{ request('sim_cycle', 36) }}" class="rounded bg-gray-900 border-gray-600" title="Menit siklus">
        <button class="px-3 py-1 bg-indigo-600 text-white rounded">Simulasikan</button>
    </form>
    <p>MF = {{ $sim['numerator'] }}/{{ $sim['denominator'] }} ({{ number_format($sim['mf_bps'] / 100, 1) }}%) — {{ $sim['state'] }}. {{ $sim['recommendation'] }}</p>
</div>
</div></div>
@endsection
