<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">Operasional Parkir</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Okupansi zona, sesi berjalan, pendapatan harian, dan beban validasi tenant
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('mall.parking.gate.entry', ['property_id' => $property->id]) }}"
                   class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all">
                    Buka Layar Gate
                </a>
                <a href="{{ route('mall.parking.footfall', ['property_id' => $property->id]) }}"
                   class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-md transition-all">
                    Analitik Kunjungan
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Kartu ringkasan --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                    $cards = [
                        ['label' => 'Okupansi Saat Ini', 'value' => $stats['occupancy_rate'].'%', 'sub' => $stats['total_occupied'].' / '.$stats['total_capacity'].' slot', 'color' => 'blue'],
                        ['label' => 'Kendaraan di Dalam', 'value' => number_format($stats['active_sessions'], 0, ',', '.'), 'sub' => $stats['exits_today'].' keluar hari ini', 'color' => 'indigo'],
                        ['label' => 'Pendapatan Hari Ini', 'value' => 'Rp '.number_format($stats['revenue_today'], 0, ',', '.'), 'sub' => $stats['lost_tickets_today'].' tiket hilang', 'color' => 'emerald'],
                        ['label' => 'Ditanggung Tenant', 'value' => 'Rp '.number_format($stats['validation_burden_today'], 0, ',', '.'), 'sub' => $stats['validated_today'].' tiket divalidasi', 'color' => 'amber'],
                    ];
                @endphp

                @foreach($cards as $card)
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-2">{{ $card['label'] }}</p>
                        <p class="text-2xl font-black text-slate-800 leading-none">{{ $card['value'] }}</p>
                        <p class="text-[11px] text-slate-400 mt-2">{{ $card['sub'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Zona --}}
                <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="font-bold text-slate-800 mb-4">Kapasitas per Zona</h3>

                    <div class="space-y-4">
                        @forelse($stats['zones'] as $zone)
                            @php
                                $rate = $zone->occupancyRate();
                                $barColor = $zone->isFull() ? 'bg-rose-500' : ($rate >= 85 ? 'bg-amber-500' : 'bg-emerald-500');
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1.5">
                                    <span class="font-bold text-slate-700">{{ $zone->code }} — {{ $zone->name }}</span>
                                    <span class="font-mono text-slate-500">{{ $zone->current_occupancy }}/{{ $zone->total_capacity }}</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full {{ $barColor }}" style="width: {{ min(100, $rate) }}%"></div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">
                                    {{ $zone->vehicle_type->label() }} · {{ $zone->availableSlots() }} slot tersisa
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400 text-center py-4">Belum ada zona parkir aktif.</p>
                        @endforelse
                    </div>

                    <div class="mt-6 pt-5 border-t border-slate-200">
                        <p class="text-xs text-slate-500 mb-1">Langganan member aktif</p>
                        <p class="text-xl font-black text-slate-800">{{ number_format($stats['active_members'], 0, ',', '.') }}</p>
                        @if($stats['expiring_members'] > 0)
                            <p class="text-[11px] text-amber-600 font-semibold mt-1">
                                {{ $stats['expiring_members'] }} akan berakhir dalam 7 hari
                            </p>
                        @endif
                        <a href="{{ route('mall.parking.members', ['property_id' => $property->id]) }}"
                           class="inline-block mt-3 text-xs font-bold text-blue-600 hover:underline">
                            Kelola langganan &rarr;
                        </a>
                    </div>
                </div>

                {{-- Sesi --}}
                <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                        <h3 class="font-bold text-slate-800">Sesi Parkir</h3>
                        <div class="flex gap-2 text-[11px] font-bold">
                            <a href="{{ route('mall.parking.index', ['property_id' => $property->id]) }}"
                               class="px-3 py-1.5 rounded-lg {{ $statusFilter === '' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600' }}">
                                Semua
                            </a>
                            <a href="{{ route('mall.parking.index', ['property_id' => $property->id, 'status' => 'active']) }}"
                               class="px-3 py-1.5 rounded-lg {{ $statusFilter === 'active' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600' }}">
                                Sedang Parkir
                            </a>
                        </div>
                    </div>

                    @if($sessions->isEmpty())
                        <p class="px-6 py-10 text-sm text-slate-400 text-center">Belum ada sesi parkir tercatat.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="text-left px-6 py-3 font-bold">Tiket</th>
                                        <th class="text-left px-4 py-3 font-bold">Plat</th>
                                        <th class="text-left px-4 py-3 font-bold">Masuk</th>
                                        <th class="text-left px-4 py-3 font-bold">Keluar</th>
                                        <th class="text-right px-4 py-3 font-bold">Tarif</th>
                                        <th class="text-center px-6 py-3 font-bold">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($sessions as $session)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-6 py-3 font-mono text-slate-600">{{ $session->ticket_number }}</td>
                                            <td class="px-4 py-3 font-mono font-bold text-slate-800">
                                                {{ $session->plate_number }}
                                                @if($session->member)
                                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 text-[9px] font-bold">MEMBER</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-slate-500">{{ $session->entry_time->format('d/m H:i') }}</td>
                                            <td class="px-4 py-3 text-slate-500">{{ $session->exit_time?->format('d/m H:i') ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-slate-800">
                                                Rp {{ number_format($session->total_fee, 0, ',', '.') }}
                                                @if($session->discount_amount > 0)
                                                    <span class="block text-[9px] text-emerald-600">
                                                        validasi −{{ number_format($session->discount_amount, 0, ',', '.') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold {{ $session->status->badgeClass() }}">
                                                    {{ $session->status->label() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 border-t border-slate-200">
                            {{ $sessions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
