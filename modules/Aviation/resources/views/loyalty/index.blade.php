<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                Program Loyalitas Aviasi (Frequent Flyer Program)
            </h2>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                Buku Besar Poin Miles & Anti-Double Dipping
            </span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm dark:bg-emerald-950 dark:border-emerald-800 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Ringkasan Anggota & Saldo Miles -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Anggota FFP</div>
                    <div class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ number_format($vm->totalMembers()) }}</div>
                    <p class="mt-1 text-xs text-gray-400">Akun frequent flyer terdaftar</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Anggota Status Elit</div>
                    <div class="mt-2 text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ number_format($vm->elitMembersCount()) }}</div>
                    <p class="mt-1 text-xs text-gray-400">Tier Silver, Gold, Platinum</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Saldo Miles Beredar</div>
                    <div class="mt-2 text-2xl font-extrabold text-blue-600 dark:text-blue-400">{{ number_format($vm->totalMilesOutstanding()) }}</div>
                    <p class="mt-1 text-xs text-gray-400">Kewajiban liabilitas poin</p>
                </div>
            </div>

            <!-- Tabel Anggota Frequent Flyer -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Buku Rekening Anggota</h3>
                    <p class="text-xs text-gray-500">Mutasi saldo miles, tingkat tier, dan masa berlaku poin</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Nomor Keanggotaan</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Maskapai Afiliasi</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Tier Status</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Saldo Miles</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500">Kedaluwarsa Tier</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($vm->accounts as $acc)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $acc->membership_number }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $acc->airline->name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase
                                            @if ($acc->current_tier === 'platinum') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                            @elseif ($acc->current_tier === 'gold') bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                            @elseif ($acc->current_tier === 'silver') bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-100
                                            @else bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                            @endif">
                                            {{ $acc->current_tier }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-gray-900 dark:text-gray-100 font-medium">
                                        {{ number_format($acc->redeemable_miles) }} miles
                                        <span class="block text-xs font-normal text-gray-400">Tier: {{ number_format($acc->tier_miles) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">
                                        {{ $acc->tier_expiry_date->format('d M Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">Belum ada akun frequent flyer terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-xs text-gray-500 text-right">
                                    Total Akun Ditampilkan: {{ $vm->accounts->count() }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($vm->accounts->hasPages())
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $vm->accounts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
