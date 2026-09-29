@php
    use Modules\AutoServe\Domain\Enums\BookingStatus;
    use Modules\AutoServe\Domain\Enums\EstimateStatus;

    $viewer = auth()->user();
    $isStaff = $viewer && in_array($viewer->role, ['admin', 'mekanik'], true);
    $isOwner = $viewer && (int) $booking->customer_id === (int) $viewer->id;
    $walletBalance = $viewer ? (int) $viewer->walletAccount('IDR')->cached_balance : 0;
@endphp

<div class="glass-card rounded-2xl p-6 space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-white">Estimasi & Escrow Perbaikan</h3>
            <p class="text-xs text-slate-400 mt-0.5">
                Dana estimasi ditahan platform, dicairkan sesuai biaya aktual saat servis selesai.
            </p>
        </div>
        @if($activeEstimate)
        <span class="px-3 py-1 rounded-full text-[11px] font-bold border {{ $activeEstimate->status->badgeClasses() }}">
            {{ $activeEstimate->status->label() }}
        </span>
        @endif
    </div>

    @if($activeEstimate)
    <div class="rounded-xl border border-slate-700/60 overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-slate-800/60 text-slate-400">
                <tr>
                    <th class="text-left px-3 py-2 font-semibold">Item</th>
                    <th class="text-center px-3 py-2 font-semibold">Qty</th>
                    <th class="text-right px-3 py-2 font-semibold">Harga</th>
                    <th class="text-right px-3 py-2 font-semibold">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                @foreach($activeEstimate->items as $item)
                <tr>
                    <td class="px-3 py-2 text-slate-200">
                        {{ $item['name'] }}
                        <span class="ml-1 text-[10px] text-slate-500">{{ $item['type'] === 'service' ? 'jasa' : 'sparepart' }}</span>
                    </td>
                    <td class="px-3 py-2 text-center text-slate-300 font-mono">{{ $item['qty'] }}</td>
                    <td class="px-3 py-2 text-right text-slate-300 font-mono">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right text-white font-mono">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-800/40">
                <tr>
                    <td colspan="3" class="px-3 py-2 text-right text-slate-300 font-semibold">Total Estimasi</td>
                    <td class="px-3 py-2 text-right text-amber-400 font-mono font-bold">{{ $activeEstimate->formatted_total }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($activeEstimate->backorder_order_id && $booking->isWaitingParts())
    <div class="p-3 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-300 text-xs">
        Sebagian sparepart dipesan lebih dulu (backorder internal bengkel). Pengerjaan dilanjutkan setelah barang diterima.
    </div>
    @endif

    <div class="flex flex-wrap gap-2">
        @if($isStaff && $activeEstimate->status === EstimateStatus::Draft)
        <form method="POST" action="{{ route('estimates.send', $activeEstimate) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold transition-all">
                Kirim ke Customer
            </button>
        </form>
        @endif

        @if($isStaff && $booking->isWaitingParts() && $activeEstimate->backorder_order_id)
        <form method="POST" action="{{ route('estimates.receiveBackorder', $activeEstimate) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white text-xs font-bold transition-all">
                Sparepart Backorder Diterima
            </button>
        </form>
        @endif
    </div>

    {{-- Persetujuan customer --}}
    @if($isOwner && $activeEstimate->status === EstimateStatus::Sent)
    <div x-data="{ pin: '', showReject: false }" class="space-y-3 pt-4 border-t border-slate-700/50">
        <div class="flex items-center justify-between text-xs">
            <span class="text-slate-400">Saldo dompet</span>
            <span class="font-mono text-white">Rp {{ number_format($walletBalance, 0, ',', '.') }}</span>
        </div>

        @if($walletBalance < $activeEstimate->total)
        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
            Saldo kurang Rp {{ number_format($activeEstimate->total - $walletBalance, 0, ',', '.') }}.
            <a href="{{ route('wallet.index') }}" class="underline font-semibold">Top up dompet &rarr;</a>
        </div>
        @else
        <form method="POST" action="{{ route('estimates.approve', $activeEstimate) }}" class="space-y-3">
            @csrf
            <label class="block text-xs text-slate-400">PIN Dompet (6 digit)</label>
            <input type="password" name="pin" x-model="pin" maxlength="6" inputmode="numeric" required
                class="w-full px-3 py-2.5 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-center text-lg font-mono tracking-[0.4em]"
                placeholder="••••••">
            <button type="submit" :disabled="pin.length !== 6"
                class="w-full px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 text-slate-950 text-xs font-bold transition-all">
                Setujui & Tahan Dana {{ $activeEstimate->formatted_total }}
            </button>
        </form>
        @endif

        <button type="button" @click="showReject = ! showReject"
            class="w-full px-4 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 text-xs font-bold transition-all">
            Tolak Estimasi
        </button>

        <form x-show="showReject" x-cloak x-transition method="POST" action="{{ route('estimates.reject', $activeEstimate) }}" class="space-y-2">
            @csrf
            <textarea name="reason" rows="2" maxlength="1000"
                class="w-full px-3 py-2 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-xs"
                placeholder="Alasan penolakan (opsional)"></textarea>
            <button type="submit" class="px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-xs font-bold transition-all">
                Kirim Penolakan
            </button>
        </form>
    </div>
    @endif

    {{-- Persetujuan biaya tambahan --}}
    @if($isOwner && $booking->status === BookingStatus::AwaitingExtraApproval->value)
    @php $extra = (int) $activeEstimate->extra_amount; @endphp
    <div x-data="{ pin: '' }" class="space-y-3 pt-4 border-t border-slate-700/50">
        <div class="p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20 text-yellow-300 text-xs">
            Biaya aktual melebihi estimasi. Selisih yang perlu dibayar:
            <strong class="font-mono">Rp {{ number_format($extra, 0, ',', '.') }}</strong>
            (total akhir Rp {{ number_format((int) $booking->grand_total, 0, ',', '.') }}).
        </div>

        @if($walletBalance < $extra)
        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
            Saldo kurang Rp {{ number_format($extra - $walletBalance, 0, ',', '.') }}.
            <a href="{{ route('wallet.index') }}" class="underline font-semibold">Top up dompet &rarr;</a>
        </div>
        @else
        <form method="POST" action="{{ route('bookings.approveExtra', $booking) }}" class="space-y-3">
            @csrf
            <input type="password" name="pin" x-model="pin" maxlength="6" inputmode="numeric" required
                class="w-full px-3 py-2.5 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-center text-lg font-mono tracking-[0.4em]"
                placeholder="••••••">
            <button type="submit" :disabled="pin.length !== 6"
                class="w-full px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 text-slate-950 text-xs font-bold transition-all">
                Bayar Selisih & Selesaikan Servis
            </button>
        </form>
        @endif
    </div>
    @endif
    @endif

    {{-- Penyusunan estimasi oleh mekanik --}}
    @if($isStaff && (! $activeEstimate || $activeEstimate->status === EstimateStatus::Draft) && ! $booking->isCompleted() && ! $booking->isInvoiced() && ! $booking->isCancelled())
    <form method="POST" action="{{ route('estimates.store', $booking) }}"
        x-data="estimateBuilder(@js($services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'price' => (int) $s->price])), @js($allSpareparts->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (int) $p->price])))"
        class="space-y-3 pt-4 border-t border-slate-700/50">
        @csrf

        <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Susun Estimasi</h4>
            <div class="flex gap-2">
                <button type="button" @click="addRow('service')" class="px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-[11px] font-semibold hover:text-amber-400">+ Jasa</button>
                <button type="button" @click="addRow('part')" class="px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-[11px] font-semibold hover:text-amber-400">+ Sparepart</button>
            </div>
        </div>

        <template x-for="(row, index) in rows" :key="row.key">
            <div class="grid grid-cols-12 gap-2 items-center">
                <input type="hidden" :name="`items[${index}][type]`" :value="row.type">

                <select :name="`items[${index}][ref_id]`" x-model.number="row.ref_id" @change="syncPrice(row)"
                    class="col-span-6 px-3 py-2 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-xs">
                    <option value="">— pilih {{ '' }}<span x-text="row.type"></span> —</option>
                    <template x-for="option in optionsFor(row.type)" :key="option.id">
                        <option :value="option.id" x-text="option.name"></option>
                    </template>
                </select>

                <input type="number" :name="`items[${index}][qty]`" x-model.number="row.qty" min="1" required
                    class="col-span-2 px-3 py-2 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-xs font-mono text-center">

                <input type="number" :name="`items[${index}][unit_price]`" x-model.number="row.unit_price" min="0" required
                    class="col-span-3 px-3 py-2 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-xs font-mono text-right">

                <button type="button" @click="removeRow(index)" class="col-span-1 text-rose-400 hover:text-rose-300 text-lg leading-none">&times;</button>
            </div>
        </template>

        <div class="flex items-center justify-between pt-2 border-t border-slate-700/40 text-xs">
            <span class="text-slate-400">Total Estimasi</span>
            <span class="font-mono font-bold text-amber-400" x-text="'Rp ' + total.toLocaleString('id-ID')"></span>
        </div>

        <label class="flex items-center gap-2 text-xs text-slate-400">
            <input type="checkbox" name="send_now" value="1" checked class="rounded bg-slate-800 border-slate-600">
            Langsung kirim ke customer untuk disetujui
        </label>

        <button type="submit" :disabled="rows.length === 0"
            class="w-full px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-40 text-slate-950 text-xs font-bold transition-all">
            Simpan Estimasi
        </button>
    </form>
    @endif
</div>
