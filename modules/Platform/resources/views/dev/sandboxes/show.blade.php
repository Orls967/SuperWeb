<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Sandbox Environment Detail') }}: {{ $sandbox->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow rounded-lg">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <div class="text-xs text-gray-500">Slug</div>
                        <div class="font-mono text-sm">{{ $sandbox->slug }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Tier</div>
                        <div class="text-sm font-semibold">{{ $sandbox->tier }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Status</div>
                        <div class="text-sm font-bold text-sky-600">{{ $sandbox->status }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Expires At</div>
                        <div class="text-sm">{{ $sandbox->expires_at?->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                </div>

                <h4 class="font-semibold text-gray-800 mb-3">Transition History (Append-Only)</h4>
                <div class="border rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Waktu</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Dari</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Ke</th>
                                <th class="px-4 py-2 text-left text-xs text-gray-500">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($sandbox->transitions as $trans)
                                <tr>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $trans->created_at->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $trans->from_status }}</td>
                                    <td class="px-4 py-2 font-mono text-xs font-semibold">{{ $trans->to_status }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-700">{{ $trans->reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

