<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('B2B Wholesale Marketplace & Surplus Asset Auction') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Marketplace & Escrow Health</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-indigo-50 p-4 rounded border border-indigo-200">
                        <span class="text-xs text-gray-500 uppercase">System Status</span>
                        <p class="text-xl font-bold text-indigo-700">{{ $audit['status'] }}</p>
                    </div>
                    <div class="bg-blue-50 p-4 rounded border border-blue-200">
                        <span class="text-xs text-gray-500 uppercase">Wholesale Catalog</span>
                        <p class="text-xl font-bold text-blue-700">{{ $audit['catalog_items_count'] }} Items</p>
                    </div>
                    <div class="bg-amber-50 p-4 rounded border border-amber-200">
                        <span class="text-xs text-gray-500 uppercase">Active Auctions</span>
                        <p class="text-xl font-bold text-amber-700">{{ $audit['active_auctions_count'] }} Lots</p>
                    </div>
                    <div class="bg-emerald-50 p-4 rounded border border-emerald-200">
                        <span class="text-xs text-gray-500 uppercase">Held Escrow</span>
                        <p class="text-xl font-bold text-emerald-700">Rp {{ number_format($audit['total_escrow_held_idr'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Surplus Asset & Machinery Auctions</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Lot #</th>
                                <th class="px-4 py-2 text-left">Title</th>
                                <th class="px-4 py-2 text-left">Type</th>
                                <th class="px-4 py-2 text-right">Starting Bid</th>
                                <th class="px-4 py-2 text-right">Highest Bid</th>
                                <th class="px-4 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($auctions as $auc)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $auc->lot_number }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $auc->title }}</td>
                                    <td class="px-4 py-2">{{ $auc->asset_type }}</td>
                                    <td class="px-4 py-2 text-right">Rp {{ number_format($auc->starting_bid_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-indigo-600">Rp {{ number_format($auc->current_highest_bid_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 uppercase">{{ $auc->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-400">No active surplus auctions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
