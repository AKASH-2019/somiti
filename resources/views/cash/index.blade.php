<x-layouts.app title="কালেক্টর ক্যাশ হিসাব">
    <x-page-header title="কালেক্টর ক্যাশ হিসাব" :description="'সবার হাতে মোট ' . bn_money($total)">
        <x-slot:actions>
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
        </x-slot:actions>
    </x-page-header>

    <p class="mb-4 text-sm text-ink-soft">হাতে থাকা টাকা = প্রারম্ভিক ক্যাশ + সংগ্রহ + হস্তান্তর গ্রহণ − হস্তান্তর প্রদান ± সমন্বয়। অপেক্ষমাণ হস্তান্তর হিসাবে ধরা হয় না।</p>

    <div class="panel overflow-x-auto">
        <table class="register">
            <thead>
            <tr>
                <th>নাম</th><th class="num">প্রারম্ভিক</th><th class="num">মোট সংগ্রহ</th><th class="num">হস্তান্তর গ্রহণ</th>
                <th class="num">হস্তান্তর প্রদান</th><th class="num">সমন্বয়</th><th class="num">হাতে থাকা টাকা</th><th class="num">অপেক্ষমাণ</th><th class="no-print"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $r)
                <tr @class(['text-ink-soft' => ! $r->user->is_active])>
                    <td>
                        <span class="font-semibold">{{ $r->user->name }}</span>
                        <span class="block text-xs text-ink-soft">{{ $r->user->role->label() }}{{ $r->user->is_active ? '' : ' · নিষ্ক্রিয়' }}</span>
                    </td>
                    <td class="num whitespace-nowrap">{{ bn_money($r->opening) }}</td>
                    <td class="num whitespace-nowrap">{{ bn_money($r->collection) }}</td>
                    <td class="num whitespace-nowrap">{{ bn_money($r->handover_in) }}</td>
                    <td class="num whitespace-nowrap">{{ bn_money($r->handover_out) }}</td>
                    <td class="num whitespace-nowrap">{{ bn_money($r->adjustment) }}</td>
                    <td @class(['num whitespace-nowrap text-[17px] font-bold', 'text-danger' => \App\Support\Money::toPaisa($r->balance) < 0, 'text-ledger-900' => \App\Support\Money::toPaisa($r->balance) >= 0])>{{ bn_money($r->balance) }}</td>
                    <td class="num whitespace-nowrap text-sm">
                        @if (\App\Support\Money::toPaisa($r->pending_out) > 0)<span class="block text-pending">প্রদান {{ bn_money($r->pending_out) }}</span>@endif
                        @if (\App\Support\Money::toPaisa($r->pending_in) > 0)<span class="block text-pending">গ্রহণ {{ bn_money($r->pending_in) }}</span>@endif
                    </td>
                    <td class="no-print text-right"><a href="{{ route('admin.cash.show', $r->user) }}" class="text-sm font-semibold text-ledger-700 hover:underline">বিবরণী</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
