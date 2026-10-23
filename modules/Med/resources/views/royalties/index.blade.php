@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Box Office & Creator Talent Residual Royalties</h2>
            <p class="mt-1 text-sm text-gray-400">Distribusi Pendapatan Box Office Streaming & Pelunasan Hak Residual Talent</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Projects Box Office -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Distribusi Box Office Proyek</h3>
                    <div class="space-y-3">
                        @forelse($projects as $proj)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">{{ $proj->title }}</div>
                                <div class="text-xs text-gray-400">Total Box Office: Rp {{ number_format($proj->box_office_revenue_minor, 0, ',', '.') }}</div>
                                <form action="{{ route('med.royalties.distribute', $proj->id) }}" method="POST" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="number" name="box_office_revenue_minor" value="100000000" class="flex-1 bg-gray-900 border border-gray-700 rounded p-1 text-white text-xs">
                                    <button type="submit" class="py-1 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded transition">
                                        Distribusi
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada proyek.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Talent Contracts -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Kontrak Residual Talent ({{ $talentContracts->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($talentContracts as $talent)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white text-sm font-medium">{{ $talent->contract_code }} ({{ $talent->role_name }})</div>
                                    <div class="text-xs text-gray-400">Talent: {{ $talent->talent_party_id }} • Royalti: Rp {{ number_format($talent->calculated_royalty_minor, 0, ',', '.') }}</div>
                                </div>
                                <div>
                                    @if($talent->payout_status === 'APPROVED')
                                        <form action="{{ route('med.royalties.talent.settle', $talent->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="py-1 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs rounded transition">
                                                Bayar Payout
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded bg-gray-900 text-gray-400">{{ $talent->payout_status }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada kontrak talent.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
