@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Detail Riset: {{ $grant->title }}</h2>
                    <p class="mt-1 text-sm text-gray-400">Kode: {{ $grant->grant_code }} • PI: {{ $grant->principal_investigator_name }}</p>
                </div>
                <a href="{{ route('edu.grants.index') }}" class="text-xs text-gray-400 hover:text-white">&larr; Kembali</a>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6">
                <h3 class="text-md font-medium text-white mb-4">Milestone Pencairan Dana Riset</h3>
                <div class="space-y-3">
                    @forelse($grant->milestones as $milestone)
                        <div class="p-4 bg-gray-700/50 rounded-lg border border-gray-600 flex justify-between items-center">
                            <div>
                                <div class="text-white font-medium">{{ $milestone->milestone_title }} ({{ $milestone->milestone_code }})</div>
                                <div class="text-xs text-indigo-400 mt-1">Nilai: Rp {{ number_format($milestone->disbursement_amount_minor, 0, ',', '.') }}</div>
                                @if($milestone->disbursed_at)
                                    <div class="text-xs text-gray-400 mt-0.5">Dicairkan pada: {{ $milestone->disbursed_at }}</div>
                                @endif
                            </div>
                            <div>
                                @if($milestone->status === 'PENDING')
                                    <form action="{{ route('edu.grants.disburse', $milestone->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded transition">
                                            Cairkan Dana Milestone
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs px-2 py-1 rounded bg-emerald-900 text-emerald-200">SUDAH DICAIRKAN</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada milestone terdaftar.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
