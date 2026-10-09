@extends('layouts.app')

@section('title', 'Dashboard Obligasi Kontrak')
@section('subtitle', 'Milestone jatuh tempo, kontrak akan berakhir, dan notice period')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-xl font-bold text-white">Dashboard Obligasi Kontrak</h1>
        <form method="GET" action="{{ route('contract.obligations') }}" class="flex items-center gap-2">
            <label for="days" class="text-sm text-gray-400">Horizon</label>
            <select id="days" name="days" onchange="this.form.submit()" class="rounded-lg border-slate-600 bg-slate-900 text-white text-sm">
                @foreach([7, 14, 30, 60, 90] as $option)
                    <option value="{{ $option }}" @selected($days === $option)>{{ $option }} hari</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="space-y-8">
            <section class="bg-white shadow-sm sm:rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Milestone / Obligasi jatuh tempo</h3>
                    <span class="rounded-full bg-amber-100 text-amber-800 px-3 py-1 text-xs font-semibold">{{ $dueMilestones->count() }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">Kontrak</th>
                                <th class="px-6 py-3 text-left">Obligasi</th>
                                <th class="px-6 py-3 text-left">Entitas</th>
                                <th class="px-6 py-3 text-left">Penanggung jawab</th>
                                <th class="px-6 py-3 text-left">Jatuh tempo</th>
                                <th class="px-6 py-3 text-right">Nominal (IDR)</th>
                                <th class="px-6 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($dueMilestones as $milestone)
                                <tr>
                                    <td class="px-6 py-3"><a class="text-indigo-600 hover:underline" href="{{ route('contract.show', $milestone->contract) }}">{{ $milestone->contract?->contract_number }}</a></td>
                                    <td class="px-6 py-3 font-medium text-gray-900">{{ $milestone->title }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $milestone->contract?->legalEntity?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $milestone->responsible_role }}</td>
                                    <td class="px-6 py-3 {{ $milestone->due_date->isPast() ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $milestone->due_date->format('d M Y') }}</td>
                                    <td class="px-6 py-3 text-right">{{ number_format($milestone->amount_idr) }}</td>
                                    <td class="px-6 py-3">{{ $milestone->status->label() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400">Tidak ada milestone dalam horizon ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white shadow-sm sm:rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Kontrak akan berakhir</h3>
                    <span class="rounded-full bg-red-100 text-red-800 px-3 py-1 text-xs font-semibold">{{ $expiring->count() }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr><th class="px-6 py-3 text-left">Nomor</th><th class="px-6 py-3 text-left">Judul</th><th class="px-6 py-3 text-left">Entitas</th><th class="px-6 py-3 text-left">Berakhir</th><th class="px-6 py-3 text-left">Auto-renew</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($expiring as $contract)
                                <tr>
                                    <td class="px-6 py-3"><a class="text-indigo-600 hover:underline" href="{{ route('contract.show', $contract) }}">{{ $contract->contract_number }}</a></td>
                                    <td class="px-6 py-3 text-gray-900">{{ $contract->title }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $contract->legalEntity?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-red-600">{{ $contract->end_date?->format('d M Y') }}</td>
                                    <td class="px-6 py-3">{{ $contract->auto_renew ? 'Ya' : 'Tidak' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">Tidak ada kontrak yang akan berakhir dalam horizon ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white shadow-sm sm:rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Notice period telah dimulai</h3>
                    <span class="rounded-full bg-purple-100 text-purple-800 px-3 py-1 text-xs font-semibold">{{ $noticeDue->count() }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr><th class="px-6 py-3 text-left">Nomor</th><th class="px-6 py-3 text-left">Judul</th><th class="px-6 py-3 text-left">Notice (hari)</th><th class="px-6 py-3 text-left">Tanggal akhir</th><th class="px-6 py-3 text-left">Auto-renew</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($noticeDue as $contract)
                                <tr>
                                    <td class="px-6 py-3"><a class="text-indigo-600 hover:underline" href="{{ route('contract.show', $contract) }}">{{ $contract->contract_number }}</a></td>
                                    <td class="px-6 py-3 text-gray-900">{{ $contract->title }}</td>
                                    <td class="px-6 py-3">{{ $contract->notice_period_days }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $contract->end_date?->format('d M Y') }}</td>
                                    <td class="px-6 py-3">{{ $contract->auto_renew ? 'Ya' : 'Tidak' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">Tidak ada notice period aktif dalam horizon ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
    </div>
</div>
@endsection
