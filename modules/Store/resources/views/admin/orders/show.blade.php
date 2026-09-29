@extends('layouts.app')

@section('title', 'Admin: Detail Order ' . $order->number)
@section('subtitle', 'Rincian transaksi, verifikasi ledger, dan status operasional')

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('store.admin.orders.index') }}" class="hover:text-amber-400">Admin Toko</a>
        <span>/</span>
        <a href="{{ route('store.admin.orders.index') }}" class="hover:text-amber-400">Pesanan</a>
        <span>/</span>
        <span class="text-slate-200">{{ $order->number }}</span>
    </nav>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Left Details (8 cols) --}}
        <div class="lg:col-span-8 space-y-6">
            {{-- Order Summary Card --}}
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-700/60">
                    <div>
                        <span class="text-xs text-slate-400">Nomor Pesanan</span>
                        <h2 class="text-xl font-bold font-mono text-white">{{ $order->number }}</h2>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $order->status->badgeClasses() }}">
                        {{ $order->status->label() }}
                    </span>
                </div>

                {{-- Items Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-slate-700/60">
                            <tr>
                                <th class="py-2.5">Produk</th>
                                <th class="py-2.5 text-center">Qty</th>
                                <th class="py-2.5 text-right">Harga Satuan</th>
                                <th class="py-2.5 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40 text-slate-300">
                            @foreach($order->items as $item)
                            <tr>
                                <td class="py-3 font-semibold text-white">
                                    {{ $item->name_snapshot }}
                                    @if($item->product?->is_car)
                                        <span class="ml-1 text-[10px] text-violet-400">(Unit Mobil)</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center font-mono">{{ $item->qty }}</td>
                                <td class="py-3 text-right font-mono">Rp {{ number_format($item->price_snapshot, 0, ',', '.') }}</td>
                                <td class="py-3 text-right font-mono font-bold text-amber-400">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Totals --}}
                <div class="pt-4 border-t border-slate-700/60 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Subtotal Produk</span>
                        <span class="font-mono text-white">{{ $order->formatted_subtotal }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Ongkos Kirim</span>
                        <span class="font-mono text-white">{{ $order->formatted_shipping_fee }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-slate-700/60 text-sm font-bold text-white">
                        <span>Grand Total</span>
                        <span class="font-mono font-extrabold text-amber-400">{{ $order->formatted_grand_total }}</span>
                    </div>
                </div>
            </div>

            {{-- Ledger & Payment Intent Audit Card --}}
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Audit Pembayaran Ledger & Payment Intent
                </h3>

                @forelse($order->paymentIntents as $intent)
                <div class="p-4 rounded-2xl bg-slate-900 border border-slate-700/60 text-xs space-y-2 font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Intent UUID:</span>
                        <span class="text-white">{{ $intent->uuid }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Status Intent:</span>
                        <span class="text-amber-400 font-bold uppercase">{{ $intent->status->value }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Ledger Capture Tx ID:</span>
                        <span class="text-indigo-400">{{ $intent->capture_transaction_id ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Idempotency Key:</span>
                        <span class="text-slate-400 truncate max-w-xs">{{ $intent->idempotency_key }}</span>
                    </div>
                </div>
                @empty
                <p class="text-xs text-slate-500">Tidak ada payment intent tercatat.</p>
                @endforelse
            </div>
        </div>

        {{-- Right Actions & Shipping (4 cols) --}}
        <div class="lg:col-span-4 space-y-6">
            {{-- Customer Card --}}
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Data Pelanggan</h4>
                <div class="text-xs text-slate-300 space-y-1">
                    <p class="font-bold text-white">{{ $order->user?->name }}</p>
                    <p class="text-slate-400">{{ $order->user?->email }}</p>
                    <p class="text-slate-400">Telepon: {{ $order->shipping_address['phone'] ?? '-' }}</p>
                </div>

                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 pt-3 border-t border-slate-700/40">Alamat Kirim</h4>
                <p class="text-xs text-slate-300 leading-relaxed bg-slate-900/60 p-3 rounded-xl border border-slate-700/40">
                    {{ $order->shipping_address['address'] ?? '-' }}<br>
                    {{ $order->shipping_address['city'] ?? '-' }}, {{ $order->shipping_address['postal_code'] ?? '-' }}
                </p>
            </div>

            {{-- Admin Control Actions --}}
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tindakan Admin</h4>

                @if(in_array($order->status, [\Modules\Store\Domain\Enums\OrderStatus::PAID, \Modules\Store\Domain\Enums\OrderStatus::PROCESSING], true))
                <form method="POST" action="{{ route('store.admin.orders.ship', $order) }}" class="space-y-3">
                    @csrf
                    <label class="text-xs text-slate-300 block">Kirim Pesanan (Input No. Resi)</label>
                    <input
                        type="text"
                        name="tracking_number"
                        placeholder="Contoh: JNE-12345678"
                        required
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs font-mono"
                    >
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-purple-500 hover:bg-purple-400 text-white font-bold text-xs transition-all">
                        Tandai Dikirim
                    </button>
                </form>
                @endif

                @if($order->status === \Modules\Store\Domain\Enums\OrderStatus::SHIPPED)
                <form method="POST" action="{{ route('store.admin.orders.complete', $order) }}">
                    @csrf
                    <button type="submit" onclick="return confirm('Selesaikan pesanan ini?')" class="w-full py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition-all shadow-md shadow-emerald-500/20">
                        Tandai Selesai (Completed)
                    </button>
                </form>
                @endif

                @if(! in_array($order->status, [\Modules\Store\Domain\Enums\OrderStatus::COMPLETED, \Modules\Store\Domain\Enums\OrderStatus::CANCELLED, \Modules\Store\Domain\Enums\OrderStatus::REFUNDED], true))
                <form method="POST" action="{{ route('store.admin.orders.cancel', $order) }}" class="pt-3 border-t border-slate-700/40">
                    @csrf
                    <input type="hidden" name="reason" value="Dibatalkan oleh Administrator">
                    <button type="submit" onclick="return confirm('Apakah Anda yakin ingin membatalkan dan merefund pesanan ini?')" class="w-full py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 font-semibold text-xs transition-all">
                        Batalkan & Refund Pesanan
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
