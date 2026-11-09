<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 leading-tight">
                Papan Tugas Perawat Bangsal (375px)
            </h2>
            @if($activeAlerts > 0)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 animate-bounce">
                    ⚠️ {{ $activeAlerts }} Order Terlambat
                </span>
            @endif
        </div>
    </x-slot>

    <!-- Kontainer Maksimal Mobile 375px -->
    <div class="py-4 px-2 max-w-sm mx-auto space-y-4">
        <!-- Kartu Quick Action -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-sm">
            <div class="font-bold text-indigo-900 mb-1">Shift Jaga Bangsal Terpadu</div>
            <p class="text-xs text-indigo-700">Periksa order tertunda dan laporkan jika terdapat keterlambatan tindakan klinis > 15 menit.</p>
        </div>

        <!-- Daftar Kartu Tugas Clinical Pathway -->
        <div class="space-y-3">
            @if($orders->isEmpty())
                <div class="bg-white rounded-lg p-6 text-center text-gray-500 shadow-sm">
                    <p class="text-sm">Tidak ada tugas pathway aktif saat ini.</p>
                </div>
            @else
                @foreach($orders as $order)
                    <div class="bg-white rounded-lg p-4 shadow-sm border {{ $order->status === 'DELAYED' ? 'border-amber-400 bg-amber-50/30' : 'border-gray-200' }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="inline-flex px-2 py-0.5 text-xs font-bold rounded bg-gray-100 text-gray-700">
                                    {{ $order->order_type }}
                                </span>
                                <span class="text-xs font-mono text-gray-400 ml-1">#{{ $order->order_code }}</span>
                            </div>
                            @php
                                $statusBadge = match(strtoupper($order->status)) {
                                    'DELAYED' => 'bg-amber-100 text-amber-800 border-amber-300 font-bold',
                                    'IN_PROGRESS' => 'bg-blue-100 text-blue-800',
                                    'SCHEDULED' => 'bg-emerald-100 text-emerald-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 text-xs rounded border {{ $statusBadge }}">
                                {{ strtoupper($order->status) }}
                            </span>
                        </div>

                        <div class="mt-2 text-sm font-semibold text-gray-900">
                            {{ $order->description }}
                        </div>

                        <div class="mt-2 text-xs text-gray-500 flex justify-between">
                            <span>Pasien: <strong class="text-gray-700">{{ $order->encounter?->patient?->name ?? 'Pasien Bangsal' }}</strong></span>
                            <span class="font-mono">{{ \Carbon\Carbon::parse($order->scheduled_at)->format('H:i') }} WIB</span>
                        </div>

                        @if($order->delay_alert_sent)
                            <div class="mt-2 text-xs bg-amber-100 text-amber-800 p-2 rounded flex items-center gap-1 font-medium">
                                <span>⚠️ Keterlambatan melebihi toleransi 15 menit telah terdeteksi.</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>

        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>
