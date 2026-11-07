@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">Mine Block Planning (M1 Weighted Time Phasing)</h2>
                    <p class="text-sm text-gray-400">Perencanaan target produksi blok tambang, alokasi shift, dan penyesuaian faktor cuaca/hujan.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Form Buat Plan Baru -->
            <div class="bg-gray-700/50 p-6 rounded-lg mb-8 border border-gray-600">
                <h3 class="text-lg font-semibold text-white mb-4">Buat Rencana Blok Tambang Baru</h3>
                <form action="{{ route('mining.plans.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Mining Site</label>
                        <select name="site_id" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->code }} - {{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Mining Pit</label>
                        <select name="pit_id" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                            @foreach($pits as $pit)
                                <option value="{{ $pit->id }}">{{ $pit->pit_code }} - {{ $pit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Periode (YYYY-MM)</label>
                        <input type="text" name="period_month" value="{{ date('Y-m') }}" required pattern="\d{4}-\d{2}" class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Komoditas</label>
                        <input type="text" name="commodity" value="NICKEL" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Target Tonnage (kg)</label>
                        <input type="number" name="target_tonnage_kg" value="50000000" min="1000" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Target Kadar / Grade (ppm / Ni%)</label>
                        <input type="number" name="target_grade_ppm" value="18000" min="0" required class="mt-1 block w-full rounded bg-gray-800 border-gray-600 text-white">
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded shadow">
                            Generate & Time-Phase Plan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Daftar Rencana Tambang -->
            <div class="bg-gray-700/40 rounded-lg p-6 border border-gray-600">
                <h3 class="text-lg font-semibold text-white mb-4">Daftar Rencana Blok Tambang Aktif</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-800 text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="p-3">Kode Plan</th>
                                <th class="p-3">Site / Pit</th>
                                <th class="p-3">Periode</th>
                                <th class="p-3">Komoditas</th>
                                <th class="p-3">Target (Ton)</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Shift Count</th>
                                <th class="p-3 text-right">Aksi Penyesuaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($plans as $plan)
                                <tr class="hover:bg-gray-750">
                                    <td class="p-3 font-mono font-medium text-indigo-400">{{ $plan->plan_code }}</td>
                                    <td class="p-3">{{ $plan->site?->code ?? '-' }} / {{ $plan->pit_id }}</td>
                                    <td class="p-3">{{ $plan->period_month }}</td>
                                    <td class="p-3">{{ $plan->commodity }}</td>
                                    <td class="p-3">{{ number_format($plan->target_tonnage_kg / 1000, 2) }} T</td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-emerald-900/60 text-emerald-300 border border-emerald-600">
                                            {{ $plan->status->value }}
                                        </span>
                                    </td>
                                    <td class="p-3">{{ $plan->shifts->count() }} shift</td>
                                    <td class="p-3 text-right">
                                        <form action="{{ route('mining.plans.replan', $plan) }}" method="POST" class="inline-flex items-center space-x-2">
                                            @csrf
                                            <input type="number" name="additional_rain_hours" min="0" max="12" placeholder="Jam Hujan" class="w-24 text-xs rounded bg-gray-900 border-gray-600 text-white p-1">
                                            <button type="submit" class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white text-xs rounded font-semibold">
                                                Re-Plan Shift
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-gray-400">Belum ada data rencana tambang yang tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $plans->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
