<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Surge Pricing Ber-Guardrail — Fase 81 &amp; Item 64.1</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if (session('warning'))<div class="rounded-md bg-yellow-50 p-4 text-yellow-800">{{ session('warning') }}</div>@endif
            @if (session('error'))<div class="rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                <strong>Fase 81 &amp; Item 64.1:</strong> Aturan tick deterministik (faktor stok, permintaan, umur simpan, musim, okupansi), batas langkah ±1.000 bps, guardrail [floor, min(ceiling, HET)], penolakan tercatat di <code>pric_tick_rejections</code>, dan proyeksi elastisitas permintaan.
            </div>

            <!-- Kalkulasi Manual & Preview -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Kalkulasi Manual Surge Pricing</h3>
                <form method="POST" action="{{ route('pricing.surge.calculate') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700">SKU</label>
                        <input name="sku" required placeholder="Contoh: SKU-V1" class="mt-1 w-full rounded border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Harga Dasar (Rp)</label>
                        <input type="number" name="base_price_idr" value="10000" class="mt-1 w-full rounded border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Floor Price (Rp)</label>
                        <input type="number" name="floor_price_idr" value="8000" class="mt-1 w-full rounded border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Ceiling / HET (Rp)</label>
                        <input type="number" name="het_price_idr" value="14000" class="mt-1 w-full rounded border-gray-300 text-sm">
                    </div>
                    <div class="md:col-span-4 flex justify-end">
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Hitung &amp; Rekam Tick</button>
                    </div>
                </form>
            </section>

            <!-- Ticks Terkini -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Daftar Tick Harga Terkini</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-4 py-2">Waktu</th>
                                <th class="px-4 py-2">SKU</th>
                                <th class="px-4 py-2">Harga Efektif</th>
                                <th class="px-4 py-2">Harga Mentah</th>
                                <th class="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($ticks as $t)
                                <tr>
                                    <td class="px-4 py-2">{{ $t->tick_time?->toDateTimeString() }}</td>
                                    <td class="px-4 py-2 font-mono">{{ $t->sku }}</td>
                                    <td class="px-4 py-2 font-bold">Rp {{ number_format($t->price_idr) }}</td>
                                    <td class="px-4 py-2 text-gray-500">Rp {{ number_format($t->raw_price_idr ?? $t->price_idr) }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $t->status === 'dibatasi' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $t->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-center text-gray-500">Belum ada data tick tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

