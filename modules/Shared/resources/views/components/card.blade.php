@props([
    'title' => null,
    'subtitle' => null,
    'action' => null,
    'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'bg-slate-800/80 backdrop-blur-xl border border-slate-700/50 rounded-2xl shadow-xl transition-all ' . $padding]) }}>
    @if($title || $action)
        <div class="flex items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-700/40">
            <div>
                @if($title)
                    <h3 class="text-lg font-semibold text-slate-100">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-xs text-slate-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @if($action)
                <div class="flex items-center gap-2">
                    {{ $action }}
                </div>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
