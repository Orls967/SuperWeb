@extends('layouts.app')

@section('title', 'Cloud Kitchen Virtual Brand Routing')
@section('subtitle', 'Orkestrator Rute Pesanan Multi-Brand Virtual ke Stasiun Dapur Berdasarkan Kapasitas & Radius')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Rute Order Katering Baru</h3>
            <form action="{{ route('cloud_kitchen.operations.route-order') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Paket Langganan</label>
                    <select name="subscription_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                        @foreach($subscriptions as $s)
                            <option value="{{ $s->id }}">{{ $s->subscription_code }} ({{ $s->package_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Slot Jadwal Makan</label>
                    <select name="meal_slot" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                        <option value="lunch">Makan Siang (11:30 - 13:00)</option>
                        <option value="dinner">Makan Malam (18:00 - 19:30)</option>
                        <option value="breakfast">Sarapan (07:00 - 08:30)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Biaya Porsi (IDR Integer)</label>
                    <input type="number" name="meal_cost_idr" value="45000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-orange-600 hover:bg-orange-500 text-white font-bold rounded-xl text-sm">
                    Rute Pesanan ke Dapur
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Pesanan Masuk & Stasiun Dapur Tujuan</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Order</th>
                            <th class="p-3">Stasiun Dapur</th>
                            <th class="p-3">Slot Makan</th>
                            <th class="p-3">Biaya</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($orders as $ord)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 font-mono font-bold text-orange-400 text-xs">{{ $ord->order_code }}</td>
                                <td class="p-3 text-xs">{{ optional($ord->kitchen)->name ?? '-' }}</td>
                                <td class="p-3 uppercase text-xs">{{ $ord->meal_slot }}</td>
                                <td class="p-3 font-mono text-xs">Rp {{ number_format($ord->meal_cost_idr) }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded bg-amber-500/20 text-amber-400 uppercase">
                                        {{ $ord->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">Belum ada pesanan dirutekan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
