{{-- Generic modal. Trigger slot opens it; default slot is the body. --}}
@props(['title', 'open' => false])
<div x-data="{ open: @js((bool) $open) }" class="inline">
    <span @click="open = true">{{ $trigger }}</span>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-ledger-950/50" @click="open = false"></div>
            <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-5 shadow-xl" x-trap.noscroll="open">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 class="text-lg font-bold text-ink">{{ $title }}</h2>
                    <button type="button" class="text-ink-soft hover:text-ink" @click="open = false" aria-label="বন্ধ করুন">✕</button>
                </div>
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
