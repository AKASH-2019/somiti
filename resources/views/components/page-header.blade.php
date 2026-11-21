@props(['title', 'description' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4 border-b-4 border-double border-rule pb-3">
    <div>
        <h1 class="text-2xl leading-tight font-bold text-ledger-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-[15px] text-ink-soft">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="no-print flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
