@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Surplus Asset Dutch Auctions</h1>
            <p class="text-gray-600 dark:text-gray-400">High-value industrial equipment and excess inventory reverse auctions with time-based price decay.</p>
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
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Create Dutch Auction</h2>
            <form action="{{ route('b2b.auction.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lot Number</label>
                    <input type="text" name="lot_number" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="LOT-DUTCH-901">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Seller ID</label>
                    <input type="text" name="seller_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="PLANT-CILEGON-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lot Title</label>
                    <input type="text" name="title" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="Surplus Heavy Excavator Cat 320D">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asset Type</label>
                    <select name="asset_type" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm">
                        <option value="factory_machine">Factory Machine</option>
                        <option value="vehicle">Heavy Vehicle</option>
                        <option value="surplus_inventory">Surplus Raw Materials</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Starting Price (IDR)</label>
                    <input type="number" name="starting_bid_idr" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="500000000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reserve Floor Price (IDR)</label>
                    <input type="number" name="reserve_price_idr" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="350000000">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium">Launch Dutch Auction</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Active & Recent Dutch Auctions</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">Lot #</th>
                            <th class="p-3">Title</th>
                            <th class="p-3">Starting</th>
                            <th class="p-3">Floor</th>
                            <th class="p-3">Winning Bidder</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($auctions ?? [] as $auc)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $auc->lot_number }}</td>
                            <td class="p-3">{{ $auc->title }}</td>
                            <td class="p-3">Rp {{ number_format($auc->starting_bid_idr, 0, ',', '.') }}</td>
                            <td class="p-3">Rp {{ number_format($auc->reserve_price_idr, 0, ',', '.') }}</td>
                            <td class="p-3">{{ $auc->winning_bidder_id ?? 'None' }}</td>
                            <td class="p-3">
                                <span class="px-2 py-1 text-xs rounded-full {{ $auc->status === 'awarded' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $auc->status }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500">No surplus auctions currently active.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
