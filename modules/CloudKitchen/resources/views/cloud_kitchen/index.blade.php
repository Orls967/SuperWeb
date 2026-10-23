@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Cloud Kitchen & Corporate Catering Management</h2>
            <p class="mt-1 text-sm text-gray-400">Kapasitas Dapur Terpusat, Manajemen Langganan Katering Karyawan & Dispatched Meals</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Kitchen Facilities -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Fasilitas Cloud Kitchen</h3>
                    <div class="space-y-3">
                        @forelse($kitchens as $kitchen)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="flex justify-between items-center">
                                    <span class="text-white font-medium">{{ $kitchen->name }} ({{ $kitchen->kitchen_code }})</span>
                                    <span class="px-2 py-0.5 text-xs rounded {{ $kitchen->is_active ? 'bg-emerald-800 text-emerald-200' : 'bg-red-800 text-red-200' }}">
                                        {{ $kitchen->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-gray-400 flex justify-between">
                                    <span>Beban Jam Ini: <strong class="text-cyan-400">{{ $kitchen->current_hourly_load }}</strong> / {{ $kitchen->hourly_capacity }} porsi/jam</span>
                                    <span>Radius: {{ $kitchen->service_radius_km }} km</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada data dapur terpusat.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Subscriptions & Dispatch -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Langganan Katering Aktif</h3>
                    <div class="space-y-3">
                        @forelse($subscriptions as $sub)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="flex justify-between items-center">
                                    <span class="text-white font-medium">{{ $sub->package_name }}</span>
                                    <span class="text-xs text-amber-400 font-semibold">{{ $sub->subscription_code }}</span>
                                </div>
                                <div class="mt-1 text-xs text-gray-400">
                                    User: {{ $sub->user->name ?? 'User #'.$sub->user_id }} | Kuota: {{ $sub->daily_quota }} porsi/hari | Rp {{ number_format($sub->daily_meal_price_idr) }}/porsi
                                </div>
                                <div class="mt-3">
                                    <form action="{{ route('cloud_kitchen.subscriptions.dispatch', $sub) }}" method="POST" class="flex items-center space-x-2">
                                        @csrf
                                        <select name="meal_slot" class="text-xs bg-gray-900 border border-gray-600 rounded px-2 py-1 text-white">
                                            <option value="lunch">Lunch</option>
                                            <option value="dinner">Dinner</option>
                                            <option value="breakfast">Breakfast</option>
                                        </select>
                                        <button type="submit" class="py-1 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                                            Dispatch Porsi
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada langganan katering.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="mt-6 bg-gray-700/50 p-4 rounded-lg">
                <h3 class="text-md font-medium text-gray-200 mb-3">Pesanan Katering Terbaru</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left text-gray-300">
                        <thead class="text-gray-400 uppercase bg-gray-800 border-b border-gray-700">
                            <tr>
                                <th class="px-3 py-2">Order Code</th>
                                <th class="px-3 py-2">Langganan</th>
                                <th class="px-3 py-2">Kitchen</th>
                                <th class="px-3 py-2">Slot</th>
                                <th class="px-3 py-2">Biaya</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $ord)
                                <tr class="border-b border-gray-700/50">
                                    <td class="px-3 py-2 font-mono text-cyan-400">{{ $ord->order_code }}</td>
                                    <td class="px-3 py-2">{{ $ord->subscription->package_name ?? 'N/A' }}</td>
                                    <td class="px-3 py-2">{{ $ord->kitchen->name ?? 'N/A' }}</td>
                                    <td class="px-3 py-2 uppercase">{{ $ord->meal_slot }}</td>
                                    <td class="px-3 py-2">Rp {{ number_format($ord->meal_cost_idr) }}</td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded text-2xs font-semibold bg-emerald-900/60 text-emerald-300">
                                            {{ $ord->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-center text-gray-500">Belum ada pesanan katering.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
