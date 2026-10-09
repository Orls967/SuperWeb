<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ketertelusuran Lot — {{ $lot->lot_number }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif

            <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                <p class="text-sm text-indigo-800">{{ $lot->material?->code }} · {{ $lot->qty }} · status {{ $lot->status }}</p>
                <a href="{{ route('manufacturing.quality.index') }}" class="text-sm text-indigo-700 underline">← QMS</a>
            </div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">39.5 Mundur — ke bahan baku & pemasok</h3>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr>
                    <th class="p-2 text-left">Lot bahan</th><th class="p-2 text-left">Material</th>
                    <th class="p-2 text-right">Qty</th><th class="p-2 text-left">Sumber</th>
                </tr></thead><tbody>
                    @forelse ($backward['inputs'] as $row)
                        <tr class="border-t"><td class="p-2">{{ $row['lot'] }}</td><td class="p-2">{{ $row['material'] }}</td><td class="p-2 text-right">{{ $row['qty'] }}</td><td class="p-2">{{ $row['source'] }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="p-2 text-gray-500">Tidak ada jejak bahan (lot bukan hasil produksi atau issue tercatat tanpa alokasi lot).</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">39.5 Maju — penerima / pelanggan</h3>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr>
                    <th class="p-2 text-right">Order</th><th class="p-2 text-right">Item</th>
                    <th class="p-2 text-right">User</th><th class="p-2 text-right">Qty</th>
                </tr></thead><tbody>
                    @forelse ($forward as $row)
                        <tr class="border-t"><td class="p-2 text-right">{{ $row['order'] }}</td><td class="p-2 text-right">{{ $row['item'] }}</td><td class="p-2 text-right">{{ $row['user'] ?? '—' }}</td><td class="p-2 text-right">{{ $row['qty'] }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="p-2 text-gray-500">Lot belum terjual (belum ada jejak penjualan).</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
