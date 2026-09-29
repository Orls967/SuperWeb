@extends('layouts.app')

@section('title', 'Cicil dengan Jaminan Crypto')
@section('subtitle', 'Simulasikan HODL-to-Drive sebelum mengajukan pembiayaan')

@section('content')
@php
    $grandTotal = (int) $product->price + $handlingFee;
    $defaultSymbol = $assets->first()?->symbol ?? 'BTC';
    $defaultDp = (int) floor($grandTotal * 0.2);
@endphp

<div x-data="loanSimulator({
        grandTotal: {{ $grandTotal }},
        tenors: @js($tenors),
        prices: @js($prices),
        holdings: @js($holdings),
        maxLtv: {{ $maxLtv }},
        annualRate: {{ $annualRate }},
        walletBalance: {{ $walletBalance }},
        downPayment: {{ old('down_payment', $defaultDp) }},
        defaultSymbol: '{{ old('collateral_symbol', $defaultSymbol) }}'
    })" class="space-y-6">

    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('store.catalog.index') }}" class="hover:text-amber-400">Toko</a>
        <span>/</span>
        <a href="{{ route('store.products.show', $product->slug) }}" class="hover:text-amber-400">{{ $product->name }}</a>
        <span>/</span>
        <span class="text-slate-200">HODL-to-Drive</span>
    </nav>

    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('finance.loans.store', $product) }}" @submit="submitting = true">
        @csrf
        <input type="hidden" name="idempotency_key" :value="idempotencyKey">
        <input type="hidden" name="tenor_months" :value="tenorMonths">
        <input type="hidden" name="collateral_symbol" :value="symbol">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Simulator --}}
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 overflow-hidden">
                    <img src="{{ $product->primary_image }}" alt="{{ $product->name }}" class="w-full h-48 object-cover bg-slate-900">
                    <div class="p-6 space-y-2">
                        <h2 class="text-xl font-extrabold text-white">{{ $product->name }}</h2>
                        <div class="flex flex-wrap gap-4 text-xs text-slate-400">
                            <span>Harga unit <span class="font-mono text-white">{{ $product->formatted_price }}</span></span>
                            <span>Handling <span class="font-mono text-white">Rp {{ number_format($handlingFee, 0, ',', '.') }}</span></span>
                            <span>Total <span class="font-mono text-amber-400">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span></span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-5">
                    <h3 class="text-base font-bold text-white">Atur Skema Pembiayaan</h3>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-slate-300 block">Uang Muka (DP)</label>
                        <input type="number" name="down_payment" x-model.number="downPayment" min="0" :max="grandTotal" step="1000000" required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm font-mono">
                        <p class="text-[11px] text-slate-400">
                            Saldo dompet: <span class="font-mono">Rp {{ number_format($walletBalance, 0, ',', '.') }}</span>
                        </p>
                        <template x-if="! hasEnoughCash">
                            <p class="text-[11px] text-rose-400">
                                DP melebihi saldo dompet.
                                <a href="{{ route('wallet.index') }}" class="underline">Top up dulu &rarr;</a>
                            </p>
                        </template>
                        @error('down_payment')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-slate-300 block">Tenor</label>
                        <div class="grid grid-cols-4 gap-2">
                            <template x-for="tenor in tenors" :key="tenor">
                                <button type="button" @click="tenorMonths = tenor"
                                    class="py-2.5 rounded-xl text-xs font-bold border transition-all"
                                    :class="tenorMonths === tenor
                                        ? 'bg-amber-500 border-amber-400 text-slate-950'
                                        : 'bg-slate-900 border-slate-700 text-slate-300 hover:text-amber-400'">
                                    <span x-text="tenor + ' bln'"></span>
                                </button>
                            </template>
                        </div>
                        @error('tenor_months')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-slate-300 block">Aset Kolateral</label>
                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            @foreach($assets as $asset)
                            <button type="button" @click="symbol = '{{ $asset->symbol }}'"
                                class="py-2.5 rounded-xl text-xs font-bold border transition-all"
                                :class="symbol === '{{ $asset->symbol }}'
                                    ? 'bg-amber-500 border-amber-400 text-slate-950'
                                    : 'bg-slate-900 border-slate-700 text-slate-300 hover:text-amber-400'">
                                {{ $asset->symbol }}
                            </button>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-slate-400">
                            Holding kamu: <span class="font-mono" x-text="formatQty(holding) + ' ' + symbol"></span>
                        </p>
                        @error('collateral_symbol')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-2xl border border-slate-700/60 overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-900/60 text-slate-400">
                                <tr>
                                    <th class="text-left px-3 py-2 font-semibold">Cicilan</th>
                                    <th class="text-right px-3 py-2 font-semibold">Pokok</th>
                                    <th class="text-right px-3 py-2 font-semibold">Bunga</th>
                                    <th class="text-right px-3 py-2 font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/40">
                                <template x-for="row in schedulePreview" :key="row.sequence">
                                    <tr>
                                        <td class="px-3 py-2 text-slate-300 font-mono" x-text="'ke-' + row.sequence"></td>
                                        <td class="px-3 py-2 text-right text-slate-300 font-mono" x-text="formatIdr(row.principal)"></td>
                                        <td class="px-3 py-2 text-right text-slate-300 font-mono" x-text="formatIdr(row.interest)"></td>
                                        <td class="px-3 py-2 text-right text-white font-mono" x-text="formatIdr(row.amount)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p class="px-3 py-2 text-[11px] text-slate-500 bg-slate-900/40" x-show="tenorMonths > 6" x-cloak>
                            Menampilkan 6 cicilan pertama dari <span x-text="tenorMonths"></span>.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Ringkasan & konfirmasi --}}
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-amber-500/30 p-6 space-y-4">
                    <h3 class="text-base font-bold text-white">Ringkasan Pembiayaan</h3>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Pokok Pinjaman</span>
                            <span class="font-mono text-white" x-text="formatIdr(principal)"></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Bunga Flat {{ number_format($annualRate * 100, 1) }}%/tahun</span>
                            <span class="font-mono text-white" x-text="formatIdr(totalInterest)"></span>
                        </div>
                        <div class="flex justify-between items-baseline pt-3 border-t border-slate-700/60">
                            <span class="font-bold text-white">Cicilan / Bulan</span>
                            <span class="text-xl font-mono font-extrabold text-amber-400" x-text="formatIdr(monthlyAmount)"></span>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/60 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Kolateral Dibutuhkan</span>
                            <span class="font-mono text-white" x-text="formatQty(requiredCollateral) + ' ' + symbol"></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>LTV Pembukaan</span>
                            <span class="font-mono text-emerald-400">{{ (int) ($maxLtv * 100) }}%</span>
                        </div>
                        <template x-if="! hasEnoughCollateral">
                            <p class="text-[11px] text-rose-400">
                                Holding <span x-text="symbol"></span> belum cukup.
                                <a href="{{ route('crypto.market.index') }}" class="underline">Beli kripto dulu &rarr;</a>
                            </p>
                        </template>
                    </div>

                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Kolateral dikunci di akun jaminan platform, bukan dijual. LTV dipantau tiap menit:
                        di atas {{ (int) (\Modules\Finance\Domain\Models\Loan::MARGIN_CALL_LTV * 100) }}% kamu kena margin call,
                        di atas {{ (int) (\Modules\Finance\Domain\Models\Loan::LIQUIDATION_LTV * 100) }}% kolateral dilikuidasi otomatis.
                    </p>
                </div>

                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
                    <h3 class="text-sm font-bold text-white">Data Pengiriman Unit</h3>

                    <div class="space-y-3">
                        <input type="text" name="recipient_name" required value="{{ old('recipient_name', auth()->user()->name) }}"
                            placeholder="Nama penerima"
                            class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                        @error('recipient_name')<p class="text-xs text-red-400">{{ $message }}</p>@enderror

                        <input type="text" name="phone" required value="{{ old('phone') }}" placeholder="Nomor telepon"
                            class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                        @error('phone')<p class="text-xs text-red-400">{{ $message }}</p>@enderror

                        <textarea name="address" rows="2" required placeholder="Alamat lengkap"
                            class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">{{ old('address') }}</textarea>
                        @error('address')<p class="text-xs text-red-400">{{ $message }}</p>@enderror

                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="city" required value="{{ old('city') }}" placeholder="Kota"
                                class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                            <input type="text" name="postal_code" required value="{{ old('postal_code') }}" placeholder="Kode pos"
                                class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm font-mono">
                        </div>
                        @error('city')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        @error('postal_code')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2 pt-3 border-t border-slate-700/60">
                        <label class="text-xs font-semibold text-slate-300 block">PIN Dompet (6 digit)</label>
                        <input type="password" name="pin" x-model="pin" maxlength="6" inputmode="numeric" required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white text-center text-2xl font-mono tracking-[0.5em]"
                            placeholder="••••••">
                        @error('pin')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" :disabled="! canSubmit"
                        class="w-full py-3.5 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition-all">
                        <span x-show="! submitting">Kunci Kolateral & Ajukan Pembiayaan</span>
                        <span x-show="submitting" x-cloak>Memproses…</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
