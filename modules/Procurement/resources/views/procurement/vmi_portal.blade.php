<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Portal Vendor-Managed Inventory (VMI) — Pemasok</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if (session('info'))<div class="rounded-md bg-blue-50 p-4 text-blue-800">{{ session('info') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                <strong>Portal VMI (Role: Supplier):</strong> Memantau persediaan SKU yang terikat kontrak aktif, mengkalkulasi titik pemesanan ulang (s, S), dan menerbitkan auto-PO terproteksi plafon anggaran.
            </div>

            <!-- Daftar Kontrak SKU Aktif -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Daftar Kontrak SKU Aktif Anda</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kode Kontrak</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">ROP (s)</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Target Qty (S)</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Harga Satuan (IDR)</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Plafon Kontrak (IDR)</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($agreements as $agr)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $agr->agreement_code }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $agr->sku }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($agr->reorder_point) }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($agr->reorder_qty) }}</td>
                                    <td class="px-4 py-2 text-right">Rp {{ number_format($agr->unit_cost_idr) }}</td>
                                    <td class="px-4 py-2 text-right">Rp {{ number_format($agr->contract_plafond_idr) }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $agr->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-4 text-center text-gray-500 italic">Belum ada kontrak SKU aktif untuk akun pemasok Anda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Riwayat VMI Replenishments -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Riwayat Usulan &amp; Auto-PO VMI</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">VMI Code</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stok Gudang</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty Pesan</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total Estimasi (IDR)</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($replenishments as $rep)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $rep->vmi_code }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $rep->sku }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($rep->current_shelf_stock) }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($rep->suggested_reorder_qty) }}</td>
                                    <td class="px-4 py-2 text-right">Rp {{ number_format($rep->total_estimated_idr) }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $rep->status === 'AUTO_ORDERED' || $rep->status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $rep->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 italic">Belum ada usulan replenishment tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
