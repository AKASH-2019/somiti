@php($role = auth()->user()->isAdmin() ? 'admin' : 'collector')
<x-layouts.app :title="'রসিদ ' . $tx->receipt_no">
    <style>
        @media print {
            @page { size: {{ $compact ? '80mm auto' : 'A4' }}; margin: {{ $compact ? '2mm' : '12mm' }}; }
            aside[aria-label], header.sticky, .no-print { display: none !important; }
            main { padding: 0 !important; }
            .receipt { border: 0 !important; }
        }
    </style>

    <div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <a href="{{ request()->fullUrlWithQuery(['layout' => null]) }}" @class(['btn-quiet', 'ring-2 ring-ledger-600' => ! $compact])>A4 রসিদ</a>
            <a href="{{ request()->fullUrlWithQuery(['layout' => 'compact']) }}" @class(['btn-quiet', 'ring-2 ring-ledger-600' => $compact])>ছোট রসিদ</a>
            <button type="button" class="btn-primary" onclick="window.print()">রসিদ প্রিন্ট করুন</button>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route($role . '.collections.create', $tx->account) }}" class="btn-quiet">আবার টাকা নিন</a>
            <a href="{{ route($role . '.collections.' . $tx->account->type->value) }}" class="btn-quiet">{{ $tx->account->type->label() }} সংগ্রহ</a>
            @can('reverse', $tx)
                <x-modal title="লেনদেন বাতিল" :open="$errors->reverse->any()">
                    <x-slot:trigger><button type="button" class="btn-quiet text-danger">লেনদেন বাতিল</button></x-slot:trigger>
                    <form method="POST" action="{{ route('admin.transactions.reverse', $tx) }}" class="space-y-4">
                        @csrf
                        <p class="text-[15px] text-ink-soft">
                            রসিদ {{ $tx->receipt_no }} ({{ bn_money($tx->amount) }}) বাতিল হবে। লেনদেন মুছবে না — "বাতিলকৃত" হিসেবে থাকবে।
                            দিন/কিস্তিগুলো আবার বকেয়া হবে@if ($tx->entry_type === \App\Enums\EntryType::Cash), এবং {{ $tx->collector->name }}-এর ক্যাশ থেকে {{ bn_money($tx->amount) }} বাদ যাবে@endif।
                        </p>
                        <x-field name="reason" label="বাতিলের কারণ" bag="reverse" required placeholder="যেমন: ভুল সদস্যের অ্যাকাউন্টে জমা" />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-quiet" @click="open = false">ফিরে যান</button>
                            <button type="submit" class="btn-danger">লেনদেন বাতিল করুন</button>
                        </div>
                    </form>
                </x-modal>
            @endcan
        </div>
    </div>

    @include('transactions.partials.receipt')
</x-layouts.app>
