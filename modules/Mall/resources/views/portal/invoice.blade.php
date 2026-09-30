<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('mall.portal.index') }}" class="p-2 bg-white border border-slate-200 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Bayar Tagihan #{{ $invoice->invoice_number }}
                </h2>
                <p class="text-sm text-slate-500 mt-1">Periode {{ $invoice->period_month }} &bull; Sisa Tagihan: <span class="font-bold text-rose-600">Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</span></p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center justify-between">
                    <span>{{ session('error') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-rose-500">&times;</button>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Invoice Details (2 cols) -->
                <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <span class="text-xs text-slate-400 font-mono">Invoice Number</span>
                            <div class="font-mono font-black text-lg text-slate-800">{{ $invoice->invoice_number }}</div>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $invoice->status->badgeClass() }}">
                            {{ $invoice->status->label() }}
                        </span>
                    </div>

                    <!-- Breakdown Lines Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-[11px] border-b border-slate-100">
                                    <th class="py-2.5 px-3">Item Tagihan</th>
                                    <th class="py-2.5 px-3 text-right">Tagihan</th>
                                    <th class="py-2.5 px-3 text-right">Terbayar</th>
                                    <th class="py-2.5 px-3 text-right">Sisa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach($invoice->lines as $line)
                                    <tr>
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-slate-800">{{ $line->description }}</div>
                                            <span class="text-[10px] text-slate-400 font-mono">{{ $line->type->label() }}</span>
                                        </td>
                                        <td class="py-3 px-3 text-right font-medium">Rp {{ number_format($line->amount, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-medium text-emerald-600">Rp {{ number_format($line->paid_amount, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right font-black {{ $line->remainingAmount() > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                            Rp {{ number_format($line->remainingAmount(), 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4 space-y-2 text-xs border border-slate-100">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal Tagihan:</span>
                            <span class="font-bold">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if($invoice->penalty_amount > 0)
                            <div class="flex justify-between text-rose-600">
                                <span>Denda Keterlambatan:</span>
                                <span class="font-bold">+ Rp {{ number_format($invoice->penalty_amount, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
                            <span>Grand Total:</span>
                            <span>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-600 font-bold">
                            <span>Sudah Dibayar:</span>
                            <span>- Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-rose-600 pt-2 border-t border-slate-200">
                            <span>Sisa Pembayaran:</span>
                            <span>Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Form (1 col) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Pembayaran via Dompet</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Saldo terpotong langsung secara real-time</p>
                    </div>

                    <!-- Wallet Balance Card -->
                    <div class="p-4 bg-indigo-50/60 rounded-xl border border-indigo-100 text-xs">
                        <span class="text-indigo-600 font-semibold block">Saldo Dompet Tersedia:</span>
                        <div class="text-xl font-black text-indigo-950 mt-1">Rp {{ number_format($walletBalance, 0, ',', '.') }}</div>
                    </div>

                    @if($invoice->remainingAmount() > 0)
                        <form action="{{ route('mall.portal.pay', $invoice->id) }}" method="POST" class="space-y-4" x-data="{ payType: 'full', customAmount: {{ $invoice->remainingAmount() }} }">
                            @csrf

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Metode Pembayaran</label>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition text-xs">
                                        <input type="radio" name="pay_option" value="full" checked @change="payType = 'full'" class="text-indigo-600">
                                        <span class="font-medium text-slate-700">Bayar Lunas Penuh (Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }})</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition text-xs">
                                        <input type="radio" name="pay_option" value="partial" @change="payType = 'partial'" class="text-indigo-600">
                                        <span class="font-medium text-slate-700">Bayar Sebagian (Cicil)</span>
                                    </label>
                                </div>
                            </div>

                            <div x-show="payType === 'partial'">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nominal yang Dibayar (Rp)</label>
                                <input type="number" name="amount" min="1000" max="{{ $invoice->remainingAmount() }}"
                                    x-bind:value="payType === 'full' ? {{ $invoice->remainingAmount() }} : customAmount"
                                    x-on:input="customAmount = $event.target.value"
                                    class="w-full text-xs rounded-xl border-slate-200 px-3 py-2 font-mono font-bold text-slate-800" required>
                                <p class="text-[10px] text-slate-400 mt-1">Maksimal: Rp {{ number_format($invoice->remainingAmount()) }}</p>
                            </div>

                            <input type="hidden" name="amount" x-bind:value="payType === 'full' ? {{ $invoice->remainingAmount() }} : customAmount" x-show="payType === 'full'">

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">PIN Dompet Transaksi</label>
                                <input type="password" name="pin" maxlength="6" placeholder="Masukkan 6 digit PIN (Default: 123456)" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2 font-mono" required>
                                <p class="text-[10px] text-slate-400 mt-1">PIN verifikasi keamanan transaksi dompet</p>
                            </div>

                            <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-[11px] text-amber-800">
                                <span class="font-bold block">Urutan Pelunasan Otomatis:</span>
                                <span>1. Denda &rarr; 2. Utilitas (Air/Listrik/AC) &rarr; 3. Service Charge &rarr; 4. Sewa Pokok</span>
                            </div>

                            <button type="submit" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-500/20 transition">
                                Konfirmasi Pembayaran Dompet
                            </button>
                        </form>
                    @else
                        <div class="p-4 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl text-center text-xs font-bold">
                            Tagihan ini telah LUNAS sepenuhnya pada {{ $invoice->paid_at?->format('d/m/Y H:i') }}.
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
