@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Multi-Party B2B Escrow</h1>
            <p class="text-gray-600 dark:text-gray-400">Ledger-backed escrow management with dual-key BAST verification and automated release.</p>
        </div>
        <a href="{{ route('b2b.dashboard') }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg">Back to Dashboard</a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Lock Escrow Funds</h2>
            <form action="{{ route('b2b.escrow.lock') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reference Type</label>
                    <select name="reference_type" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm">
                        <option value="auction_settlement">Auction Settlement</option>
                        <option value="rfq_order">RFQ Purchase Order</option>
                        <option value="equipment_lease">Equipment Lease</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reference ID</label>
                    <input type="text" name="reference_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="REF-LOT-901">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Buyer ID</label>
                    <input type="text" name="buyer_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="BUYER-CORP-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Seller ID</label>
                    <input type="text" name="seller_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="SELLER-PLANT-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Deposit Amount (IDR)</label>
                    <input type="number" name="deposit_amount_idr" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="50000000">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">Post Escrow Hold</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Escrow Holdings Ledger</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">Escrow #</th>
                            <th class="p-3">Reference</th>
                            <th class="p-3">Deposit</th>
                            <th class="p-3">Released</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">BAST Doc</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($escrows ?? [] as $esc)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $esc->escrow_number }}</td>
                            <td class="p-3">{{ $esc->reference_type }}: {{ $esc->reference_id }}</td>
                            <td class="p-3 font-semibold">Rp {{ number_format($esc->deposit_amount_idr, 0, ',', '.') }}</td>
                            <td class="p-3">Rp {{ number_format($esc->released_amount_idr, 0, ',', '.') }}</td>
                            <td class="p-3">
                                <span class="px-2 py-1 text-xs rounded-full {{ $esc->status === 'released' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $esc->status }}
                                </span>
                            </td>
                            <td class="p-3 font-mono text-xs">{{ $esc->bast_document_id ?? 'Pending' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500">No active escrow transactions found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
