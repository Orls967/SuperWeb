@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Plasma Farmer Contracts & Advances</h1>
            <p class="text-gray-600 dark:text-gray-400">Onboard plasma smallholders, record agricultural input loan advances, and establish guaranteed floor pricing.</p>
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
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Register Plasma Contract</h2>
            <form action="{{ route('agri.contracts.register') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Farmer Full Name</label>
                    <input type="text" name="full_name" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="Pak Sutrisno">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Farmer Group</label>
                    <input type="text" name="farmer_group_name" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="Koperasi Tani Makmur Subang">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Commodity</label>
                    <select name="commodity" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm">
                        <option value="cabe_merah">Cabe Merah Keriting</option>
                        <option value="beras_organik">Beras Organik Pandan Wangi</option>
                        <option value="bawang_merah">Bawang Merah Brebes</option>
                        <option value="ayam_broiler">Ayam Broiler Kemitraan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Land Area (Hectares)</label>
                    <input type="number" step="0.1" name="land_area_hectares" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="1.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target Yield (Kg)</label>
                    <input type="number" name="target_yield_kg" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="8000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Floor Price (IDR/Kg)</label>
                    <input type="number" name="guaranteed_floor_price_idr_per_kg" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="35000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Seed Advance Value (IDR)</label>
                    <input type="number" name="seed_advance_value_idr" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="5000000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fertilizer Advance Value (IDR)</label>
                    <input type="number" name="fertilizer_advance_value_idr" class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="7000000">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">Issue Plasma Contract & Disburse</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Active Farming Contracts</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-3">Contract #</th>
                            <th class="p-3">Farmer</th>
                            <th class="p-3">Commodity</th>
                            <th class="p-3">Target Yield</th>
                            <th class="p-3">Floor Price</th>
                            <th class="p-3">Advance Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($contracts ?? [] as $ctr)
                        <tr>
                            <td class="p-3 font-mono font-medium">{{ $ctr->contract_number }}</td>
                            <td class="p-3">{{ $ctr->farmer->full_name ?? 'Farmer ID: '.$ctr->farmer_id }}</td>
                            <td class="p-3 capitalize">{{ str_replace('_', ' ', $ctr->commodity) }}</td>
                            <td class="p-3">{{ number_format($ctr->target_yield_kg, 0) }} kg</td>
                            <td class="p-3">Rp {{ number_format($ctr->guaranteed_floor_price_idr_per_kg, 0, ',', '.') }}/kg</td>
                            <td class="p-3 font-semibold text-amber-600">Rp {{ number_format($ctr->total_advance_deductible_idr, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500">No active plasma contracts registered.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
