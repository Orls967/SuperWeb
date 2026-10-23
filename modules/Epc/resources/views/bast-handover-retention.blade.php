@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">BAST-1 Handover & Retention Warranty Hold</h1>
            <p class="text-gray-600 dark:text-gray-400">Formal project handover certification upon &ge;95% physical completion with automated Defect Liability Period (DLP) warranty retention hold.</p>
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
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Issue Project BAST-1</h2>
            <form action="{{ route('epc.bast.issue') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Project ID / Code</label>
                    <input type="text" name="project_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="EPC-DUTA-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">BAST Number</label>
                    <input type="text" name="bast_number" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="BAST-1-DUTA-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Warranty Period (Days)</label>
                    <input type="number" name="warranty_period_days" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="180">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Handover Notes</label>
                    <textarea name="notes" rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="Punch-list items cleared; structure commissioned."></textarea>
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium">Issue BAST-1 Handover</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Active Projects Ready for Handover</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">Project Code</th>
                            <th class="p-3">Name</th>
                            <th class="p-3">Physical Progress</th>
                            <th class="p-3">Accumulated CIP</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($projects ?? [] as $proj)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $proj->project_code }}</td>
                            <td class="p-3">{{ $proj->project_name }}</td>
                            <td class="p-3 font-semibold">{{ number_format($proj->actual_physical_progress_pct, 2) }}%</td>
                            <td class="p-3">Rp {{ number_format($proj->accumulated_cip_cost_idr, 0, ',', '.') }}</td>
                            <td class="p-3">
                                <span class="px-2 py-1 text-xs rounded-full {{ $proj->status === 'bast_issued' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $proj->status }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-500">No active EPC projects available.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
