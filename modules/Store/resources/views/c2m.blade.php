<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Konfigurator Produk C2M — Meja Modular Parametrik</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                <strong>Consumer-to-Manufacturer (C2M):</strong> Desain kustom langsung terhubung ke pabrik. Batasan parameter disaring real-time dengan CSP Arc-Consistency (AC-3) dan divalidasi dengan QuickXplain.
            </div>

            <!-- Form Desain Meja Modular -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Kustomisasi Meja Kerja Modular</h3>
                <form action="{{ route('store.c2m.configure') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="base_model" value="CUSTOM_DESK_MODULAR">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Lebar Meja (W dalam cm)</label>
                            <select name="width" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="60">60 cm</option>
                                <option value="80">80 cm</option>
                                <option value="100" selected>100 cm</option>
                                <option value="120">120 cm</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kedalaman Meja (D dalam cm)</label>
                            <select name="depth" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="40">40 cm</option>
                                <option value="60" selected>60 cm</option>
                                <option value="80">80 cm</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Material Permukaan</label>
                            <select name="material" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="kayu" selected>Kayu Solid</option>
                                <option value="besi">Besi / Steel Frame</option>
                                <option value="kaca">Kaca Tempered</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Jumlah Laci (L)</label>
                            <select name="drawers" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="3">3 Laci</option>
                                <option value="4" selected>4 Laci</option>
                                <option value="6">6 Laci</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Validasi &amp; Kunci Harga Pesanan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Riwayat Pesanan Kustom -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Pesanan Kustom C2M Anda</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kode Order</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Model Dasar</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Parameter</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Harga Terkunci (IDR)</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($orders as $ord)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $ord->c2m_code }}</td>
                                    <td class="px-4 py-2">{{ $ord->base_model }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ json_encode($ord->parameters) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($ord->final_price_idr) }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                            {{ $ord->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500 italic">Belum ada pesanan kustom yang dibuat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
