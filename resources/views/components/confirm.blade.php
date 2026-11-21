{{-- Confirmation dialog that submits a form. Slot = trigger button label. --}}
@props([
    'action',
    'method' => 'DELETE',
    'title' => 'আপনি কি নিশ্চিত?',
    'message' => '',
    'confirmLabel' => 'হ্যাঁ, নিশ্চিত',
    'tone' => 'danger',
])
<div x-data="{ open: false }" class="inline">
    <button type="button" @click="open = true" {{ $attributes->merge(['class' => $attributes->has('class') ? '' : 'btn-quiet px-3 py-1.5 text-sm']) }}>{{ $slot }}</button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-ledger-950/50" @click="open = false"></div>
            <div class="relative w-full max-w-sm rounded-lg bg-white p-5 shadow-xl" x-trap.noscroll="open">
                <h2 class="text-lg font-bold text-ink">{{ $title }}</h2>
                @if ($message)
                    <p class="mt-2 text-[15px] text-ink-soft">{{ $message }}</p>
                @endif
                <form method="POST" action="{{ $action }}" class="mt-5 flex justify-end gap-2">
                    @csrf
                    @method($method)
                    <button type="button" class="btn-quiet" @click="open = false">বাতিল</button>
                    <button type="submit" class="{{ $tone === 'danger' ? 'btn-danger' : 'btn-primary' }}">{{ $confirmLabel }}</button>
                </form>
            </div>
        </div>
    </template>
</div>
