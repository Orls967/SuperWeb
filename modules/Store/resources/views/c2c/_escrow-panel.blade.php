@php
    use Modules\Store\Domain\Enums\OrderStatus;

    $viewer = auth()->user();
    $isBuyer = $viewer && (int) $order->user_id === (int) $viewer->id;
    $isSeller = $viewer && (int) $order->seller_id === (int) $viewer->id;
@endphp

<div x-data="{ showDispute: false }" class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-indigo-500/30 p-6 lg:p-8 space-y-5">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <div class="space-y-1">
            <h3 class="text-base font-bold text-white">Transaksi Mobil Bekas (C2C) dengan Escrow</h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                Dana pembeli ditahan di rekening escrow platform. Uang baru cair ke dompet penjual
                ({{ 100 - \Modules\Store\Domain\Models\Order::C2C_PLATFORM_FEE_PERCENT }}% nilai transaksi, fee platform
                {{ \Modules\Store\Domain\Models\Order::C2C_PLATFORM_FEE_PERCENT }}%) setelah pembeli mengonfirmasi penerimaan kendaraan.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div class="bg-slate-900/60 rounded-2xl border border-slate-700/60 p-4">
            <span class="text-slate-400 block mb-1">Penjual</span>
            <span class="font-bold text-white">{{ $order->seller?->name ?? '-' }}</span>
        </div>
        <div class="bg-slate-900/60 rounded-2xl border border-slate-700/60 p-4">
            <span class="text-slate-400 block mb-1">Diterima Penjual</span>
            <span class="font-mono font-bold text-emerald-400">Rp {{ number_format($order->c2cSellerAmount(), 0, ',', '.') }}</span>
        </div>
        <div class="bg-slate-900/60 rounded-2xl border border-slate-700/60 p-4">
            <span class="text-slate-400 block mb-1">Fee Platform</span>
            <span class="font-mono font-bold text-amber-400">Rp {{ number_format($order->c2cPlatformFee(), 0, ',', '.') }}</span>
        </div>
    </div>

    @if($order->status === OrderStatus::AWAITING_CONFIRMATION && $order->auto_capture_at)
    <div class="p-3 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-300 text-xs">
        Kendaraan diserahkan pada {{ $order->handover_at?->format('d M Y, H:i') }}. Jika pembeli tidak
        mengonfirmasi sampai <strong>{{ $order->auto_capture_at->format('d M Y, H:i') }}</strong>,
        dana akan otomatis dicairkan ke penjual.
    </div>
    @endif

    @if($order->status === OrderStatus::DISPUTED)
    <div class="p-3 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-300 text-xs">
        <strong class="block">Sengketa sedang ditinjau admin.</strong>
        Alasan: {{ $order->dispute_reason }} ({{ $order->disputed_at?->format('d M Y, H:i') }})
    </div>
    @endif

    <div class="flex flex-wrap gap-3">
        @if($isSeller && $order->status === OrderStatus::AWAITING_HANDOVER)
        <form method="POST" action="{{ route('store.c2c.handover', $order) }}">
            @csrf
            <button type="submit"
                onclick="return confirm('Konfirmasi bahwa kendaraan sudah diserahkan ke pembeli?')"
                class="px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white font-bold text-xs shadow-lg shadow-indigo-500/20 transition-all">
                Tandai Kendaraan Diserahkan
            </button>
        </form>
        @endif

        @if($isBuyer && $order->status === OrderStatus::AWAITING_CONFIRMATION)
        <form method="POST" action="{{ route('store.c2c.confirm', $order) }}">
            @csrf
            <button type="submit"
                onclick="return confirm('Konfirmasi kendaraan sudah diterima? Dana escrow akan dicairkan ke penjual dan kepemilikan berpindah ke kamu.')"
                class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs shadow-lg shadow-emerald-500/20 transition-all">
                Konfirmasi Kendaraan Diterima
            </button>
        </form>
        @endif

        @if($isBuyer && in_array($order->status, [OrderStatus::AWAITING_HANDOVER, OrderStatus::AWAITING_CONFIRMATION], true))
        <button type="button" @click="showDispute = ! showDispute"
            class="px-5 py-2.5 rounded-xl bg-orange-500/10 hover:bg-orange-500/20 border border-orange-500/30 text-orange-400 font-bold text-xs transition-all">
            Ajukan Sengketa
        </button>
        @endif

        @if(($isBuyer || $isSeller) && $order->status === OrderStatus::AWAITING_HANDOVER)
        <form method="POST" action="{{ route('store.c2c.cancel', $order) }}">
            @csrf
            <input type="hidden" name="reason" value="Dibatalkan sebelum serah terima">
            <button type="submit"
                onclick="return confirm('Batalkan transaksi? Dana escrow dikembalikan penuh ke pembeli.')"
                class="px-5 py-2.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 font-bold text-xs transition-all">
                Batalkan Transaksi
            </button>
        </form>
        @endif
    </div>

    @if($isBuyer)
    <form x-show="showDispute" x-cloak x-transition method="POST" action="{{ route('store.c2c.dispute', $order) }}" class="space-y-3">
        @csrf
        <label class="text-xs font-semibold text-slate-300 block">Jelaskan masalah yang kamu temui (minimal 10 karakter)</label>
        <textarea name="reason" rows="3" required minlength="10" maxlength="1000"
            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500"
            placeholder="Contoh: kondisi kendaraan tidak sesuai deskripsi listing."></textarea>
        @error('reason')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-400 text-slate-950 font-bold text-xs transition-all">
            Kirim Pengajuan Sengketa
        </button>
    </form>
    @endif
</div>
