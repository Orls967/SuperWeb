@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">CIP to Fixed Asset Capitalization</h1>
            <p class="text-gray-600 dark:text-gray-400">Reclassify accumulated Construction In Progress (CIP) balances into corporate capitalized fixed asset registers via double-entry ledger postings.</p>
        </div>
        <a href="{{ route('epc.dashboard') }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg">Back to Dashboard</a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Capitalize Project CIP</h2>
            <form action="{{ route('epc.capitalization.capitalize') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Project ID</label>
                    <input type="text" name="project_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="EPC-DUTA-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">BAST Final Number</label>
                    <input type="text" name="bast_number" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="BAST-FINAL-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target Asset Category</label>
                    <select name="target_asset_category" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm">
                        <option value="BUILDING">Commercial Building / Mall</option>
                        <option value="INFRASTRUCTURE">Civil Infrastructure & Roadway</option>
                        <option value="MACHINERY_PLANT">Industrial Processing Plant</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asset Register Code</label>
                    <input type="text" name="created_asset_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="AST-BLD-TWR-B">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Capitalization Notes</label>
                    <textarea name="notes" rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="Final handover and capitalization into corporate fixed asset register."></textarea>
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium">Post Fixed Asset Capitalization</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Completed Capitalization History</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">BAST #</th>
                            <th class="p-3">Project</th>
                            <th class="p-3">Capitalized Value</th>
                            <th class="p-3">Asset Code</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Capitalized At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($capitalizations ?? [] as $cap)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $cap->bast_number }}</td>
                            <td class="p-3">{{ $cap->project->project_name ?? $cap->project_id }}</td>
                            <td class="p-3 font-semibold text-purple-600">Rp {{ number_format($cap->total_cip_cost_idr, 0, ',', '.') }}</td>
                            <td class="p-3 font-mono text-xs">{{ $cap->created_asset_id }}</td>
                            <td class="p-3 capitalize">{{ $cap->target_asset_category }}</td>
                            <td class="p-3">{{ $cap->capitalized_at ? $cap->capitalized_at->format('d M Y') : 'N/A' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500">No project capitalizations recorded yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
