@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Kasir Pembayaran Rawat Jalan</h2>
            <p class="mt-1 text-sm text-gray-400">Penyelesaian tagihan rawat jalan tunai & verifikasi klaim penjamin JKN.</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 p-4 bg-rose-900/50 border border-rose-500 rounded text-rose-200 text-sm space-y-1">
                    @foreach($errors->all() as $err)
                        <div>• {{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm text-left text-gray-300">
                    <thead class="bg-gray-700/50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-6 py-3">No. Tagihan</th>
                            <th class="px-6 py-3">Pasien</th>
                            <th class="px-6 py-3">Jasa Dokter</th>
                            <th class="px-6 py-3">Obat/Farmasi</th>
                            <th class="px-6 py-3">Total Tagihan</th>
                            <th class="px-6 py-3">Metode Bayar</th>
                            <th class="px-6 py-3 text-right">Aksi Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($invoices as $inv)
                            <tr class="hover:bg-gray-750">
                                <td class="px-6 py-4 font-mono font-bold text-white">{{ $inv->invoice_code }}</td>
                                <td class="px-6 py-4">{{ $inv->encounter?->patient?->name ?? 'Pasien' }}</td>
                                <td class="px-6 py-4">Rp {{ number_format($inv->doctor_fee_idr, 0, ',', '.') }}</td>
                                <td class="px-6 py-4">Rp {{ number_format($inv->medication_fee_idr, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 font-bold text-emerald-400">Rp {{ number_format($inv->total_amount_idr, 0, ',', '.') }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 text-xs rounded {{ $inv->payment_method === 'BPJS' ? 'bg-indigo-900 text-indigo-300' : 'bg-gray-700 text-gray-300' }}">
                                        {{ $inv->payment_method }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($inv->payment_method === 'CASH')
                                        <form action="{{ route('hospital.outpatient.cashier.pay', $inv) }}" method="POST" class="inline-flex items-center gap-2">
                                            @csrf
                                            <input type="number" name="cash_received_idr" value="{{ $inv->total_amount_idr }}" required min="{{ $inv->total_amount_idr }}" class="w-32 px-2 py-1 text-xs rounded bg-gray-900 border-gray-700 text-white" placeholder="Nominal Uang">
                                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded" onclick="this.form.headers = {'Idempotency-Key': 'CASH-PAY-' + '{{ $inv->id }}' + '-' + Date.now()}">
                                                Bayar Tunai
                                            </button>
                                        </form>
                                    @elseif($inv->payment_method === 'BPJS')
                                        <form action="{{ route('hospital.outpatient.bpjs.claim', $inv) }}" method="POST" class="inline-flex items-center gap-2">
                                            @csrf
                                            <input type="text" name="ina_cbg_code" value="Q-5-44-0" required class="w-24 px-2 py-1 text-xs rounded bg-gray-900 border-gray-700 text-white" placeholder="INA-CBG">
                                            <input type="number" name="package_tariff_idr" value="180000" required class="w-28 px-2 py-1 text-xs rounded bg-gray-900 border-gray-700 text-white" placeholder="Tarif Paket">
                                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded">
                                                Klaim BPJS
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    Tidak ada tagihan tertunda saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
