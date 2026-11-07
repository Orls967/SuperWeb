@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="resaleOrdersApp()">
    {{-- Main Container --}}
    <div class="max-w-6xl mx-auto space-y-8">
        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 mb-2">
                    <a href="{{ route('venue.secondary.marketplace') }}" class="hover:text-cyan-400">Marketplace Sekunder</a>
                    <span>/</span>
                    <span class="text-slate-200">Riwayat Pesanan</span>
                </div>
                <h1 class="text-3xl font-black text-white tracking-tight">Riwayat Transaksi Pasar Sekunder</h1>
                <p class="text-sm text-slate-400 mt-1 max-w-2xl">
                    Daftar re-issue barcode sah, bukti transfer hak masuk, token hash kriptografis, dan pencairan dana escrow penjualan Anda.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('venue.secondary.marketplace') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold rounded-xl border border-slate-700 text-sm">
                    ← Kembali ke Marketplace
                </a>
            </div>
        </div>

        {{-- Success/Alert notifications --}}
        @if(session('success'))
            <div class="p-4 bg-emerald-500/20 border border-emerald-500/40 rounded-xl text-emerald-300 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-lg">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tiket Dibeli Sah</div>
                <div class="mt-2 text-2xl font-black text-white">{{ $purchases->count() }} Tiket</div>
                <div class="text-[11px] text-cyan-400 mt-1">Barcode baru terenkripsi</div>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-lg">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Listing Penjualan</div>
                <div class="mt-2 text-2xl font-black text-amber-400">{{ $sales->count() }} Listing</div>
                <div class="text-[11px] text-slate-400 mt-1">Capped at 120%</div>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-lg">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Proteksi</div>
                <div class="mt-2 text-2xl font-black text-emerald-400">100% ACTIVE</div>
                <div class="text-[11px] text-emerald-300 mt-1">Anti-scalping & Single transfer</div>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-4 border-b border-slate-800">
            <button type="button" @click="activeTab = 'purchases'" 
                    class="pb-3 text-sm font-bold border-b-2 transition-all flex items-center gap-2"
                    :class="activeTab === 'purchases' ? 'border-cyan-500 text-cyan-400' : 'border-transparent text-slate-400 hover:text-slate-200'">
                <span>Tiket yang Saya Beli</span>
                <span class="px-2 py-0.5 rounded-full text-xs bg-slate-800 text-slate-300" x-text="'{{ $purchases->count() }}'"></span>
            </button>
            <button type="button" @click="activeTab = 'sales'" 
                    class="pb-3 text-sm font-bold border-b-2 transition-all flex items-center gap-2"
                    :class="activeTab === 'sales' ? 'border-cyan-500 text-cyan-400' : 'border-transparent text-slate-400 hover:text-slate-200'">
                <span>Listing Penjualan Saya</span>
                <span class="px-2 py-0.5 rounded-full text-xs bg-slate-800 text-slate-300" x-text="'{{ $sales->count() }}'"></span>
            </button>
        </div>

        {{-- Tab 1: Pembelian Tiket Sekunder --}}
        <div x-show="activeTab === 'purchases'" class="space-y-4">
            @forelse($purchases as $order)
                <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-6 hover:border-cyan-500/40 transition-all">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-cyan-400">{{ $order->resale_code }}</span>
                            <span class="px-2.5 py-0.5 rounded text-[11px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                {{ $order->status }}
                            </span>
                            <span class="text-xs text-slate-400">• Transfer 1/1 (Final)</span>
                        </div>
                        <h3 class="text-xl font-bold text-white">{{ $order->ticket->event->title ?? 'Bali Sunset Festival 2026' }}</h3>
                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                            <span>Tiket Asli: #{{ $order->ticket->ticket_number ?? 'TCK' }}</span>
                            <span>•</span>
                            <span>Hash Terkini: <span class="font-mono text-slate-300">{{ substr($order->new_ticket_hash ?? 'TCK-HASH-ROTATED', 0, 16) }}...</span></span>
                            <span>•</span>
                            <span>Diselesaikan: {{ $order->settled_at ? $order->settled_at->format('d M Y H:i') : '-' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 w-full md:w-auto justify-between md:justify-end border-t md:border-t-0 pt-4 md:pt-0 border-slate-700">
                        <div class="text-right">
                            <div class="text-xs text-slate-400">Total Dibayar:</div>
                            <div class="text-xl font-black text-white">Rp {{ number_format($order->resale_price_idr, 0, ',', '.') }}</div>
                            <div class="text-[10px] text-emerald-400 font-semibold">Termasuk PBJT 10% & Fee</div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="viewTicketBarcode('{{ $order->resale_code }}', '{{ $order->new_ticket_hash }}', '{{ $order->ticket->event->title ?? 'Event' }}')" 
                                    class="px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-bold text-xs rounded-xl shadow-md">
                                Lihat Tiket QR
                            </button>
                            <button type="button" @click="viewReceipt({{ $order->toJson() }})"
                                    class="px-3 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-semibold rounded-xl">
                                Kuitansi
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center bg-slate-800/40 border border-slate-800 rounded-2xl">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <p class="text-sm font-semibold text-slate-400">Anda belum pernah membeli tiket dari pasar sekunder.</p>
                    <a href="{{ route('venue.secondary.marketplace') }}" class="inline-flex items-center px-4 py-2 mt-3 bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold rounded-lg">
                        Jelajahi Marketplace
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Tab 2: Listing Penjualan Tiket --}}
        <div x-show="activeTab === 'sales'" class="space-y-4" style="display: none;">
            @forelse($sales as $listing)
                <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-6 hover:border-amber-500/40 transition-all">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-cyan-400">{{ $listing->resale_code }}</span>
                            @if($listing->status === 'SETTLED')
                                <span class="px-2.5 py-0.5 rounded text-[11px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                    DANA CAIR (SETTLED)
                                </span>
                            @elseif($listing->status === 'LISTED')
                                <span class="px-2.5 py-0.5 rounded text-[11px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                    SEDANG TAYANG (LISTED)
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded text-[11px] font-black uppercase tracking-wider bg-slate-500/20 text-slate-400 border border-slate-500/30">
                                    {{ $listing->status }}
                                </span>
                            @endif
                        </div>
                        <h3 class="text-xl font-bold text-white">{{ $listing->ticket->event->title ?? 'Bali Sunset Festival 2026' }}</h3>
                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                            <span>Harga Jual: Rp {{ number_format($listing->resale_price_idr, 0, ',', '.') }}</span>
                            <span>•</span>
                            <span>Penerimaan Bersih: <span class="text-emerald-400 font-bold">Rp {{ number_format($listing->seller_payout_idr > 0 ? $listing->seller_payout_idr : (int)($listing->resale_price_idr * 0.80), 0, ',', '.') }}</span></span>
                            <span>•</span>
                            <span>Didaftarkan: {{ $listing->created_at->format('d M Y H:i') }}</span>
                        </div>
                    </div>

                    <div class="text-right">
                        @if($listing->status === 'SETTLED')
                            <span class="inline-flex items-center text-xs text-emerald-400 font-bold">
                                ✓ Dana ditransfer ke rekening terdaftar
                            </span>
                        @elseif($listing->status === 'LISTED')
                            <span class="inline-flex items-center text-xs text-amber-400 font-medium">
                                Menunggu pembeli terverifikasi
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-16 text-center bg-slate-800/40 border border-slate-800 rounded-2xl">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <p class="text-sm font-semibold text-slate-400">Anda belum pernah mendaftarkan tiket untuk dijual.</p>
                    <a href="{{ route('venue.secondary.sell') }}" class="inline-flex items-center px-4 py-2 mt-3 bg-amber-500 hover:bg-amber-600 text-slate-900 text-xs font-bold rounded-lg">
                        Jual Tiket Sekarang
                    </a>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Ticket Barcode Modal --}}
    <div x-show="barcodeModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/85 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-sm w-full p-6 text-center space-y-4" @click.away="barcodeModalOpen = false">
            <h3 class="text-lg font-bold text-white" x-text="activeEventTitle"></h3>
            <div class="bg-white p-4 rounded-xl inline-block mx-auto">
                {{-- Mock QR Representation --}}
                <div class="w-48 h-48 bg-slate-950 rounded-lg flex flex-col items-center justify-center text-slate-400 p-2 border-4 border-slate-200">
                    <svg class="w-24 h-24 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    <span class="text-[9px] font-mono text-cyan-400 mt-2 font-bold tracking-widest">ENCRYPTED ROTATION</span>
                </div>
            </div>
            <div class="text-xs text-slate-400 font-mono" x-text="'Hash: ' + activeHash"></div>
            <p class="text-[11px] text-emerald-400">Barcode ini siap di-scan pada gate masuk turnstile resmi.</p>
            <button type="button" @click="barcodeModalOpen = false" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold">
                Tutup
            </button>
        </div>
    </div>

    {{-- Kuitansi Modal --}}
    <div x-show="receiptModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/85 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-md w-full p-6 space-y-4" @click.away="receiptModalOpen = false">
            <div class="border-b border-slate-800 pb-3">
                <span class="text-xs uppercase tracking-wider text-cyan-400 font-bold">Bukti Kuitansi Elektronik</span>
                <h3 class="text-lg font-bold text-white mt-1">Transaksi Pasar Sekunder</h3>
            </div>

            <template x-if="selectedReceipt">
                <div class="space-y-3 text-xs text-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Nomor Transaksi:</span>
                        <span class="font-mono text-white" x-text="selectedReceipt.resale_code"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Total Harga:</span>
                        <span class="font-bold text-white" x-text="'Rp ' + Number(selectedReceipt.resale_price_idr).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Pajak PBJT (10%):</span>
                        <span x-text="'Rp ' + Number(selectedReceipt.tax_pbjt_idr || Math.floor(selectedReceipt.resale_price_idr * 0.10)).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Platform Fee (8%):</span>
                        <span x-text="'Rp ' + Number(selectedReceipt.platform_fee_idr || Math.floor(selectedReceipt.resale_price_idr * 0.08)).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Royalti Artis (2%):</span>
                        <span x-text="'Rp ' + Number(selectedReceipt.promoter_royalty_idr || Math.floor(selectedReceipt.resale_price_idr * 0.02)).toLocaleString('id-ID')"></span>
                    </div>
                </div>
            </template>

            <button type="button" @click="receiptModalOpen = false" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold">
                Tutup Kuitansi
            </button>
        </div>
    </div>
</div>

<script>
function resaleOrdersApp() {
    return {
        activeTab: 'purchases',
        barcodeModalOpen: false,
        receiptModalOpen: false,
        selectedReceipt: null,
        activeCode: '',
        activeHash: '',
        activeEventTitle: '',

        viewTicketBarcode(code, hash, title) {
            this.activeCode = code;
            this.activeHash = hash ? hash.substring(0, 24) + '...' : 'SEC-ROTATED-HASH';
            this.activeEventTitle = title;
            this.barcodeModalOpen = true;
        },

        viewReceipt(receipt) {
            this.selectedReceipt = receipt;
            this.receiptModalOpen = true;
        }
    };
}
</script>
@endsection

{{-- Cryptographic Audit Hash Verification & Dispute Guarantee Drawer --}}
<div class="mt-8 bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-6">
    <div class="border-b border-slate-700/60 pb-3 flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Sertifikat Kriptografi Transfer Tiket (HMAC SHA-256)
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Seluruh pemindahan kepemilikan dicatat dalam rantai hash tak dapat diubah (append-only ledger).</p>
        </div>
        <span class="text-xs font-mono px-2.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">BLOCK-SEALED</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800 space-y-2">
            <div class="font-bold text-slate-200">Bagaimana Cara Kerja Rotasi Barcode?</div>
            <p class="text-slate-400 leading-relaxed">
                Saat transaksi disetujui, algoritma menghasilkan salt acak berbasis waktu mikrodetik dan menandatanganinya dengan kunci rahasia turnstile venue. Barcode lama yang disimpan oleh penjual di galeri atau screenshot otomatis <strong>gagal saat di-scan (INVALID CODE)</strong>, sehingga melindungi Anda dari penipuan dobel-scan.
            </p>
        </div>

        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800 space-y-2">
            <div class="font-bold text-slate-200">Jaminan Garansi Uang Kembali 100%</div>
            <p class="text-slate-400 leading-relaxed">
                Jika gerbang gate mengalami gangguan teknis atau terjadi pembatalan event oleh pihak promotor, dana yang tertampung di rekening escrow bursa sekunder akan dikembalikan secara penuh 100% ke metode pembayaran asal Anda tanpa potongan biaya platform.
            </p>
        </div>
    </div>
</div>
