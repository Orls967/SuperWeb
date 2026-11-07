@extends('layouts.app')

@section('content')
<div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 text-gray-300">
<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h2 class="text-xl font-bold text-white mb-4">Peringkat OEE Truk Angkut</h2>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">#</th><th class="p-2">Kode</th><th class="p-2">Ketersediaan</th><th class="p-2">Kinerja</th><th class="p-2">Kualitas</th><th class="p-2">OEE</th></tr></thead>
        <tbody class="divide-y divide-gray-700">
        @forelse($ranking as $r)
            <tr><td class="p-2">{{ $r['rank'] }}</td><td class="p-2 font-mono">{{ $r['code'] }}</td>
                <td class="p-2">{{ number_format($r['availability_bps'] / 100, 1) }}%</td><td class="p-2">{{ number_format($r['performance_bps'] / 100, 1) }}%</td>
                <td class="p-2">{{ number_format($r['quality_bps'] / 100, 1) }}%</td><td class="p-2 font-bold">{{ number_format($r['oee_bps'] / 100, 1) }}%</td></tr>
        @empty
            <tr><td colspan="6" class="p-4 text-center text-gray-400">Belum ada truk terdaftar.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h3 class="text-lg font-semibold text-white mb-3">Registri alat</h3>
    <form method="GET" class="flex gap-2 mb-3">
        <select name="site_id" class="rounded bg-gray-900 border-gray-600"><option value="">Semua site</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected(request('site_id') === $s->id)>{{ $s->site_code }}</option>@endforeach</select>
        <button class="px-3 py-1 bg-indigo-600 text-white rounded">Filter</button>
    </form>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">Kode</th><th class="p-2">Site</th><th class="p-2">Tipe</th><th class="p-2">Model</th><th class="p-2">Jam mesin</th><th class="p-2">Status</th></tr></thead>
        <tbody class="divide-y divide-gray-700">
        @foreach($equipments as $e)
            <tr><td class="p-2 font-mono">{{ $e->equipment_code }}</td><td class="p-2">{{ $e->site?->site_code }}</td><td class="p-2">{{ $e->type }}</td><td class="p-2">{{ $e->model }}</td><td class="p-2">{{ number_format($e->engine_hours) }}</td><td class="p-2">{{ $e->status }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $equipments->links() }}
</div>
</div></div>
@endsection
