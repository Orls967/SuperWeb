<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Marketplace Sirkularitas & Atribut Keberlanjutan (ESG)') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('egy.esg.marketplace.export') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Unduh Transaksi (CSV)
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Metrik Ringkasan Pasar ESG -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-emerald-500">
                    <div class="text-sm font-medium text-gray-500">Listing Aktif Tersedia</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ count($listings ?? []) }} Instrumen</div>
                    <div class="mt-1 text-xs text-emerald-600">Offset, Bundel REC, Raw Material Daur Ulang</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-indigo-500">
                    <div class="text-sm font-medium text-gray-500">Total Nilai Transaksi ESG</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">Rp {{ number_format($total_revenue_idr ?? 0, 0, ',', '.') }}</div>
                    <div class="mt-1 text-xs text-indigo-600">Diterima holding via auto-settlement ledger</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-amber-500">
                    <div class="text-sm font-medium text-gray-500">Pesanan Terselesaikan</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($total_orders_count ?? 0, 0, ',', '.') }} Order</div>
                    <div class="mt-1 text-xs text-amber-600">Tenant green lease & tamu hotel net-zero</div>
                </div>
            </div>

            <!-- Kartu Katalog Produk ESG -->
            <div>
                <h3 class="text-md font-bold text-gray-900 mb-4">Katalog Atribut Keberlanjutan & Sirkularitas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse($listings as $l)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-col justify-between border border-gray-100 hover:shadow-md transition">
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-emerald-50 text-emerald-700">{{ str_replace('_', ' ', $l->category) }}</span>
                                    <span class="text-xs font-mono text-gray-400">#{{ $l->listing_code }}</span>
                                </div>
                                <h4 class="text-lg font-bold text-gray-900">{{ $l->title }}</h4>
                                <p class="text-xs text-gray-500 mt-2 line-clamp-3">{{ $l->description }}</p>
                                <div class="mt-4 pt-3 border-t border-gray-100 flex justify-between items-baseline">
                                    <div>
                                        <div class="text-xs text-gray-400">Harga Satuan</div>
                                        <div class="text-md font-bold text-gray-900">Rp {{ number_format($l->price_minor_per_unit, 0, ',', '.') }} <span class="text-xs text-gray-500 font-normal">/ {{ $l->unit_label }}</span></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-400">Tersedia</div>
                                        <div class="text-sm font-mono font-bold text-indigo-600">{{ number_format($l->available_quantity, 0, ',', '.') }} {{ $l->unit_label }}</div>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('egy.esg.marketplace.purchase') }}" method="POST" class="mt-5">
                                @csrf
                                <input type="hidden" name="listing_id" value="{{ $l->id }}">
                                <div class="flex space-x-2">
                                    <input type="number" name="quantity" min="1" max="{{ $l->available_quantity }}" value="1" class="w-20 rounded-md border-gray-300 shadow-sm text-xs text-center font-mono">
                                    <button type="submit" class="flex-1 py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-bold uppercase tracking-wider">
                                        Beli Sekarang
                                    </button>
                                </div>
                            </form>
                        </div>
                    @empty
                        <div class="col-span-3 bg-white p-8 rounded-lg text-center text-gray-400">
                            Belum ada instrumen ESG yang didaftarkan.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Riwayat Pesanan ESG Terakhir -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-md font-bold text-gray-900 mb-4">Riwayat Pembelian & Klaim Penebusan ESG</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">No. Order</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Instrumen</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Kuantitas</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Total Nominal</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Kode Penebusan</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Status</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Sertifikat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recent_orders as $o)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-indigo-600">{{ $o->order_number }}</td>
                                    <td class="px-4 py-3">{{ $o->listing ? $o->listing->title : '-' }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format($o->purchased_quantity, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">Rp {{ number_format($o->total_amount_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center font-mono font-bold text-emerald-600">{{ $o->redemption_code }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-800 font-bold uppercase">{{ $o->claim_status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono text-gray-400 truncate max-w-xs">{{ substr($o->certificate_hash, 0, 10) }}…</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada riwayat pesanan marketplace ESG.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
