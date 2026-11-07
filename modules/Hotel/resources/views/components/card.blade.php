@props(['title' => null, 'badge' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'bg-white overflow-hidden shadow-sm rounded-lg border border-slate-200']) }}>
    @if($title || $badge || $subtitle)
        <div class="px-4 py-4 sm:px-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold leading-6 text-slate-900">{{ $title }}</h3>
                @if($subtitle)
                    <p class="mt-1 text-xs text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
            @if($badge)
                <div>{{ $badge }}</div>
            @endif
        </div>
    @endif
    <div class="px-4 py-4 sm:p-6">
        {{ $slot }}
    </div>
</div>
