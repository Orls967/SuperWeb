@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">Fleet Dispatch & Haul Cycle (M2 Need-Time Dispatcher)</h2>
                    <p class="text-sm text-gray-400">Optimalisasi alokasi armada truk ke loader/shovel secara real-time berdasarkan need-time dan match factor.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Form Otomatis Dispatch Truk -->
            <div class="bg-gray-700/50 p-6 rounded-lg mb-8 border border-gray-600">
                <h3 class="text-lg font-semibold text-white mb-4">Penugasan Otomatis Truk Angkut</h3>
                <form action="{{ route('mining.dispatch.assign') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Truk Angkut</label>
                        <select name="truck_id" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                            @foreach($trucks as $truck)
                                <option value="{{ $truck->id }}">{{ $truck->code }} ({{ $truck->name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Target Pit</label>
                        <select name="pit_id" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                            @foreach($pits as $pit)
                                <option value="{{ $pit->id }}">{{ $pit->pit_code }} - {{ $pit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Shift</label>
                        <select name="shift" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                            <option value="SHIFT_1">Shift 1 (Pagi)</option>
                            <option value="SHIFT_2">Shift 2 (Siang)</option>
                            <option value="SHIFT_3">Shift 3 (Malam)</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded shadow">
                            Jalankan M2 Dispatcher
                        </button>
                    </div>
                </form>
            </div>

            <!-- Monitoring Siklus Angkut Aktif -->
            <div class="bg-gray-700/40 rounded-lg p-6 border border-gray-600">
                <h3 class="text-lg font-semibold text-white mb-4">Siklus Angkut Real-time (Haul Cycles)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-800 text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="p-3">Cycle Code</th>
                                <th class="p-3">Truk</th>
                                <th class="p-3">Shovel</th>
                                <th class="p-3">Shift</th>
                                <th class="p-3">Muatan (Ton)</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Manual Override</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($cycles as $cycle)
                                <tr class="hover:bg-gray-750">
                                    <td class="p-3 font-mono font-medium text-indigo-400">{{ $cycle->cycle_code }}</td>
                                    <td class="p-3">{{ $cycle->truck?->code ?? '-' }}</td>
                                    <td class="p-3">{{ $cycle->shovel?->code ?? '-' }}</td>
                                    <td class="p-3">{{ $cycle->shift }}</td>
                                    <td class="p-3">{{ number_format($cycle->payload_ton, 1) }} T</td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-blue-900/60 text-blue-300 border border-blue-600">
                                            {{ $cycle->status->value }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <form action="{{ route('mining.dispatch.override', $cycle) }}" method="POST" class="inline-flex items-center space-x-2">
                                            @csrf
                                            <select name="status" class="text-xs rounded bg-gray-900 border-gray-600 text-white p-1">
                                                <option value="HAULING">HAULING</option>
                                                <option value="DUMPING">DUMPING</option>
                                                <option value="COMPLETED">COMPLETED</option>
                                                <option value="DELAYED">DELAYED</option>
                                            </select>
                                            <input type="text" name="reason" placeholder="Alasan override" required class="text-xs rounded bg-gray-900 border-gray-600 text-white p-1 w-32">
                                            <button type="submit" class="px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white text-xs rounded">
                                                Override
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-gray-400">Tidak ada siklus angkut yang tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $cycles->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
