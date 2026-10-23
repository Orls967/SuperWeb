@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">WBS Physical Progress & Monthly Claims</h1>
            <p class="text-gray-600 dark:text-gray-400">Track S-Curve physical completion, issue Monthly Progress Certificates (MC), and recognize Construction In Progress (CIP) costs.</p>
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
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Issue Monthly Certificate (MC)</h2>
            <form action="{{ route('epc.claims.record') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Project ID / Code</label>
                    <input type="text" name="project_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="EPC-DUTA-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Certificate Number</label>
                    <input type="text" name="certificate_number" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="MC-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Incremental Progress (%)</label>
                    <input type="number" step="0.01" name="certified_incremental_progress_pct" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="15.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Supervising Consultant</label>
                    <input type="text" name="supervising_consultant_name" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="PT Virama Karya Konsultan">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">Certify Progress & Post CIP Ledger</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Certified Progress Claims Ledger</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">MC #</th>
                            <th class="p-3">Project</th>
                            <th class="p-3">Progress</th>
                            <th class="p-3">Gross Claim</th>
                            <th class="p-3">5% Retention</th>
                            <th class="p-3">Net Claim</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($certificates ?? [] as $cert)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $cert->certificate_number }}</td>
                            <td class="p-3">{{ $cert->project->project_name ?? $cert->project_id }}</td>
                            <td class="p-3">{{ number_format($cert->certified_incremental_progress_pct, 2) }}% (Cum: {{ number_format($cert->certified_cumulative_progress_pct, 2) }}%)</td>
                            <td class="p-3 font-semibold">Rp {{ number_format($cert->gross_claim_amount_idr, 0, ',', '.') }}</td>
                            <td class="p-3 text-amber-600">Rp {{ number_format($cert->retention_deduction_idr, 0, ',', '.') }}</td>
                            <td class="p-3 font-bold text-blue-600">Rp {{ number_format($cert->net_payable_amount_idr, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500">No progress certificates issued yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
