@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Farmer Harvest Settlement & Payout</h1>
            <p class="text-gray-600 dark:text-gray-400">Receive harvest batches, calculate gross yield value, automatically deduct seed/fertilizer loans, and trigger ledger net payout.</p>
        </div>
        <a href="{{ route('agri.dashboard') }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg">Back to Dashboard</a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Settle Harvest Batch</h2>
            <form action="{{ route('agri.payout.settle') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contract ID</label>
                    <input type="text" name="contract_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="AGR-CTR-8812">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Collection Center</label>
                    <input type="text" name="collection_center_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="CC-SUBANG-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gross Weight (Kg)</label>
                    <input type="number" step="0.1" name="gross_weight_kg" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="1500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Grade</label>
                    <select name="grade" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm">
                        <option value="GRADE_A">Grade A (100%)</option>
                        <option value="GRADE_B">Grade B (90%)</option>
                        <option value="GRADE_C">Grade C (80%)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destination Unit</label>
                    <select name="destination_unit" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm">
                        <option value="RESTO_CK01">Central Kitchen CK01</option>
                        <option value="MFG_PLANT_01">Manufacturing Plant 01</option>
                        <option value="WMS_CENTRAL">WMS Central Distribution</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium">Settle & Post Ledger Payout</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Recent Harvest Batches & Payouts</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">Batch #</th>
                            <th class="p-3">Weight</th>
                            <th class="p-3">Grade</th>
                            <th class="p-3">Gross Value</th>
                            <th class="p-3">Loan Deducted</th>
                            <th class="p-3">Net Payout</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($batches ?? [] as $b)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $b->batch_number }}</td>
                            <td class="p-3">{{ number_format($b->gross_weight_kg, 1) }} kg</td>
                            <td class="p-3">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold {{ $b->grade === 'GRADE_A' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $b->grade }}
                                </span>
                            </td>
                            <td class="p-3 font-semibold">Rp {{ number_format($b->gross_payout_idr, 0, ',', '.') }}</td>
                            <td class="p-3 text-red-600 font-semibold">- Rp {{ number_format($b->advance_deduction_idr, 0, ',', '.') }}</td>
                            <td class="p-3 font-bold text-emerald-600">Rp {{ number_format($b->net_payout_idr, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500">No harvest batches processed yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
