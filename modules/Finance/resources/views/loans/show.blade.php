@extends('layouts.app')

@section('title', 'Detail Pembiayaan')
@section('subtitle', 'Pantau LTV, jadwal cicilan, dan kolateral kripto')

@section('content')
@php
    use Modules\Finance\Domain\Models\Loan;

    $marginPct = (int) (Loan::MARGIN_CALL_LTV * 100);
    $liquidationPct = (int) (Loan::LIQUIDATION_LTV * 100);
    $ltvPct = round($ltv * 100, 1);
    $gaugeWidth = min(100, max(0, $ltvPct));
    $gaugeColor = $ltv >= Loan::LIQUIDATION_LTV
        ? 'bg-rose-500'
        : ($ltv >= Loan::MARGIN_CALL_LTV ? 'bg-amber-500' : 'bg-emerald-500');
    $symbol = $loan->collateralAsset->symbol;
@endphp

<div class="space-y-6">
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('finance.loans.index') }}" class="hover:text-amber-400">Pembiayaan</a>
        <span>/</span>
        <span class="text-slate-200 font-mono">{{ Str::limit($loan->uuid, 13, '') }}</span>
    </nav>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">{{ session('error') }}</div>
    @endif

    @if($loan->status === \Modules\Finance\Domain\Enums\LoanStatus::MarginCall)
    <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm">
        <strong class="block">Margin Call sejak {{ $loan->margin_called_at?->format('d M Y H:i') }}</strong>
        LTV kamu melewati {{ $marginPct }}%. Tambah kolateral atau lunasi sebagian sebelum menembus {{ $liquidationPct }}%
        yang memicu likuidasi otomatis.
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Gauge LTV & ringkasan --}}
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white">Rasio LTV</h3>
                    <span class="px-3 py-1 rounded-full text-[11px] font-bold border {{ $loan->status->badgeClasses() }}">
                        {{ $loan->status->label() }}
                    </span>
                </div>

                <div class="space-y-2">
                    <div class="flex items-baseline justify-between">
                        <span class="text-3xl font-mono font-extrabold text-white">{{ number_format($ltvPct, 1) }}%</span>
                        <span class="text-xs text-slate-400">aman &lt; {{ $marginPct }}%</span>
                    </div>

                    <div class="relative h-3 rounded-full bg-slate-900 overflow-hidden">
                        <div class="h-full {{ $gaugeColor }} transition-all" style="width: {{ $gaugeWidth }}%"></div>
                        <div class="absolute top-0 h-full w-px bg-amber-300/70" style="left: {{ $marginPct }}%"></div>
                        <div class="absolute top-0 h-full w-px bg-rose-400/70" style="left: {{ $liquidationPct }}%"></div>
                    </div>

                    <div class="flex justify-between text-[10px] text-slate-500 font-mono">
                        <span>0%</span>
                        <span>{{ $marginPct }}% margin call</span>
                        <span>{{ $liquidationPct }}% likuidasi</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs pt-2 border-t border-slate-700/60">
                    <div>
                        <span class="text-slate-400 block mb-1">Sisa Pokok</span>
                        <span class="font-mono font-bold text-white">{{ $loan->formatted_outstanding }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-1">Nilai Kolateral</span>
                        <span class="font-mono font-bold text-white">
                            Rp {{ number_format((float) (string) $collateralValue->toScale(0, \Brick\Math\RoundingMode::Down), 0, ',', '.') }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-1">Kolateral Terkunci</span>
                        <span class="font-mono font-bold text-amber-400">{{ $loan->collateral_qty }} {{ $symbol }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-1">Harga {{ $symbol }}</span>
                        <span class="font-mono font-bold text-white">
                            Rp {{ number_format((float) (string) $price->toScale(0, \Brick\Math\RoundingMode::Down), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            @if($loan->status->isOpen())
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
                <h3 class="text-sm font-bold text-white">Tindakan</h3>
                <p class="text-xs text-slate-400">
                    Saldo dompet Rp {{ number_format($walletBalance, 0, ',', '.') }} ·
                    holding {{ $cryptoHolding }} {{ $symbol }}
                </p>

                <form method="POST" action="{{ route('finance.loans.payInstallment', $loan) }}">
                    @csrf
                    <button type="submit"
                        class="w-full py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition-all">
                        Bayar Cicilan Berikutnya
                        ({{ $loan->nextUnpaidInstallment()?->formatted_amount ?? '—' }})
                    </button>
                </form>

                <form method="POST" action="{{ route('finance.loans.payOff', $loan) }}">
                    @csrf
                    <button type="submit"
                        onclick="return confirm('Lunasi seluruh sisa cicilan sekarang? Kolateral akan dikembalikan ke dompet kriptomu.')"
                        class="w-full py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold text-xs transition-all">
                        Lunasi Lebih Awal
                    </button>
                </form>

                <form method="POST" action="{{ route('finance.loans.topUpCollateral', $loan) }}" class="space-y-2 pt-3 border-t border-slate-700/60">
                    @csrf
                    {{-- Kunci idempoten tetap sama saat submit ulang setelah validasi gagal --}}
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <label class="text-xs font-semibold text-slate-300 block">Tambah Kolateral ({{ $symbol }})</label>
                    <input type="number" name="qty" step="0.00000001" min="0" required
                        class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm font-mono"
                        placeholder="0.05">
                    @error('qty')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    <button type="submit"
                        class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition-all">
                        Kunci Tambahan Kolateral
                    </button>
                </form>
            </div>
            @endif
        </div>

        {{-- Jadwal cicilan --}}
        <div class="lg:col-span-7 bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-white">Jadwal Cicilan</h3>
                <span class="text-xs text-slate-400">
                    Bunga flat {{ number_format((float) $loan->interest_rate_annual * 100, 1) }}% / tahun
                </span>
            </div>

            <div class="rounded-2xl border border-slate-700/60 overflow-hidden overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-900/60 text-slate-400">
                        <tr>
                            <th class="text-left px-3 py-2 font-semibold">#</th>
                            <th class="text-left px-3 py-2 font-semibold">Jatuh Tempo</th>
                            <th class="text-right px-3 py-2 font-semibold">Pokok</th>
                            <th class="text-right px-3 py-2 font-semibold">Bunga</th>
                            <th class="text-right px-3 py-2 font-semibold">Total</th>
                            <th class="text-center px-3 py-2 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @foreach($loan->installments as $installment)
                        <tr>
                            <td class="px-3 py-2 text-slate-300 font-mono">{{ $installment->sequence }}</td>
                            <td class="px-3 py-2 text-slate-300">{{ $installment->due_date->format('d M Y') }}</td>
                            <td class="px-3 py-2 text-right text-slate-300 font-mono">{{ number_format($installment->principal_part, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-slate-300 font-mono">{{ number_format($installment->interest_part, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-white font-mono">
                                {{ number_format($installment->amount, 0, ',', '.') }}
                                @if($installment->penalty > 0)
                                <span class="block text-[10px] text-rose-400">+ denda {{ number_format($installment->penalty, 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $installment->status->badgeClasses() }}">
                                    {{ $installment->status->label() }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($loan->order)
            <a href="{{ route('store.orders.show', $loan->order) }}" class="inline-flex text-xs text-amber-400 hover:underline">
                Lihat pesanan unit kendaraan {{ $loan->order->number }} &rarr;
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
