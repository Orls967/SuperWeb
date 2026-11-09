<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Monitor Pasien Kritis & Code Blue (Fase 87.5)
            </h2>
            @if($activeCount > 0)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-600 text-white animate-pulse">
                    🚨 {{ $activeCount }} Kejadian Code Blue Aktif
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Riwayat Kejadian Code Blue & Respons Eskalasi</h3>
                <span class="text-xs text-gray-400">Jejak Kriptografis Append-Only SHA-256</span>
            </div>

            @if($events->isEmpty())
                <div class="text-center py-10 text-gray-500">
                    <p>Tidak ada riwayat kejadian Code Blue tercatat.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Kode Event</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Pasien & Ranjang</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Tingkat Eskalasi</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Timestamp Respons</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Proof Hash</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($events as $ev)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-gray-900">{{ $ev->event_no }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $ev->admission?->patient?->name ?? 'Pasien Kritis' }}</div>
                                        <div class="text-xs text-gray-400 font-mono">Bed: {{ $ev->bed?->bed_code ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-800">
                                            {{ $ev->escalation_level }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $stClass = match(strtolower($ev->status)) {
                                                'resolved', 'closed' => 'bg-emerald-100 text-emerald-800',
                                                'raised', 'detected' => 'bg-red-100 text-red-800 font-bold',
                                                'acknowledged', 'team_dispatched' => 'bg-blue-100 text-blue-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="inline-flex px-2.5 py-1 text-xs rounded-full {{ $stClass }}">
                                            {{ strtoupper($ev->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 space-y-0.5">
                                        <div>Perawat: {{ $ev->nurse_ack_at ? $ev->nurse_ack_at->format('H:i:s') : '-' }}</div>
                                        <div>Dokter: {{ $ev->doctor_ack_at ? $ev->doctor_ack_at->format('H:i:s') : '-' }}</div>
                                        <div>Selesai: {{ $ev->resolved_at ? $ev->resolved_at->format('H:i:s') : '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-400">
                                        {{ substr($ev->proof_hash, 0, 12) }}...
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $events->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

