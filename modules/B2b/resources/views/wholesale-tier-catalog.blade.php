@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Wholesale Tiered Catalog</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage B2B wholesale product pricing tiers and volume discounts.</p>
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
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Publish New Wholesale SKU</h2>
            <form action="{{ route('b2b.wholesale.publish') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vendor ID</label>
                    <input type="text" name="vendor_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="VND-ENTERPRISE-01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU Code</label>
                    <input type="text" name="sku" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="SKU-B2B-1001">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
                    <input type="text" name="product_name" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="Industrial Hydraulic Pump">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                    <input type="text" name="category" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="Machinery">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base Unit Price (IDR)</label>
                    <input type="number" name="base_price_idr" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="10000000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Min Order Qty</label>
                    <input type="number" name="min_order_qty" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Available Stock</label>
                    <input type="number" name="available_stock" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="500">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">Publish Catalog Item</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Active Wholesale Catalogs</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">SKU</th>
                            <th class="p-3">Product</th>
                            <th class="p-3">Base Price</th>
                            <th class="p-3">Min Qty</th>
                            <th class="p-3">Stock</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($catalogs ?? [] as $cat)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $cat->sku }}</td>
                            <td class="p-3">{{ $cat->product_name }}</td>
                            <td class="p-3 font-semibold">Rp {{ number_format($cat->base_price_idr, 0, ',', '.') }}</td>
                            <td class="p-3">{{ $cat->min_order_qty }}</td>
                            <td class="p-3">{{ $cat->available_stock }}</td>
                            <td class="p-3">
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">{{ $cat->status }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500">No wholesale items published yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
