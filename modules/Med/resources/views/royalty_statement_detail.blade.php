@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col space-y-6">
        <div class="p-6 bg-gray-800 shadow rounded-lg border border-gray-700">
            <h1 class="text-2xl font-bold text-white">Royalty Statement Detail</h1>
            <p class="mt-2 text-sm text-gray-400 font-mono">{{ $statement->statement_number }} (Periode {{ $statement->billing_period }})</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-800 rounded border border-gray-700">
                <span class="text-xs text-gray-400">Gross Revenue</span>
                <div class="text-lg font-bold text-white">Rp {{ number_format($statement->gross_revenue_minor, 0, ',', '.') }}</div>
            </div>
            <div class="p-4 bg-gray-800 rounded border border-gray-700">
                <span class="text-xs text-gray-400">Distribution Fee</span>
                <div class="text-lg font-bold text-amber-400">Rp {{ number_format($statement->total_distribution_fee_minor, 0, ',', '.') }}</div>
            </div>
            <div class="p-4 bg-gray-800 rounded border border-gray-700">
                <span class="text-xs text-gray-400">Advance Recouped</span>
                <div class="text-lg font-bold text-blue-400">Rp {{ number_format($statement->total_recouped_minor, 0, ',', '.') }}</div>
            </div>
            <div class="p-4 bg-gray-800 rounded border border-gray-700">
                <span class="text-xs text-gray-400">Total Royalty Distributed</span>
                <div class="text-lg font-bold text-emerald-400">Rp {{ number_format($statement->total_royalty_distributed_minor, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700 p-4">
            <h3 class="text-md font-semibold text-white mb-3">Distribusi Porsi Hak Cipta & Waterfall</h3>
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-700 text-xs text-gray-400 uppercase">
                    <tr>
                        <th class="p-3">Pihak Pemilik Hak</th>
                        <th class="p-3">Share (BPS)</th>
                        <th class="p-3">Gross Share</th>
                        <th class="p-3">Recoupment</th>
                        <th class="p-3">Net Payable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($statement->lines ?? [] as $line)
                        <tr class="border-b border-gray-700">
                            <td class="p-3 font-mono">{{ $line->rightsholder_party_id }}</td>
                            <td class="p-3">{{ $line->share_bps }} ({{ $line->share_bps / 100 }}%)</td>
                            <td class="p-3">Rp {{ number_format($line->gross_share_minor, 0, ',', '.') }}</td>
                            <td class="p-3">Rp {{ number_format($line->recoupment_deducted_minor, 0, ',', '.') }}</td>
                            <td class="p-3 font-semibold text-emerald-400">Rp {{ number_format($line->net_payable_minor, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-500">Tidak ada baris pernyataan royalti.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

