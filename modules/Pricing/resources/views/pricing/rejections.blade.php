<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Log Penolakan Guardrail Tick — Fase 81</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                <strong>Log Penolakan:</strong> Setiap tick yang melanggar guardrail mutlak [floor, min(ceiling, HET)] ditolak secara deterministik dan dicatat ke <code>pric_tick_rejections</code>.
            </div>

            <section class="rounded-lg bg-white p-6 shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-4 py-2">Waktu Slot</th>
                                <th class="px-4 py-2">SKU</th>
                                <th class="px-4 py-2">Alasan Penolakan</th>
                                <th class="px-4 py-2">Percobaan Harga</th>
                                <th class="px-4 py-2">Floor / Ceiling / HET</th>
                                <th class="px-4 py-2">Harga Terakhir Tetap</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($rejections as $r)
                                <tr>
                                    <td class="px-4 py-2">{{ $r->slot_time?->toDateTimeString() }}</td>
                                    <td class="px-4 py-2 font-mono">{{ $r->sku }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                            {{ $r->rejection_reason }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-red-600 font-semibold">Rp {{ number_format($r->attempted_price_idr) }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        F: {{ number_format($r->floor_price_idr) }} / C: {{ number_format($r->ceiling_price_idr) }} / H: {{ number_format($r->het_price_idr) }}
                                    </td>
                                    <td class="px-4 py-2 font-bold text-gray-800">Rp {{ number_format($r->last_price_idr) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-3 text-center text-gray-500">Tidak ada penolakan guardrail tercatat. Seluruh tick berada dalam rentang aman.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

