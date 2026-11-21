@php
    $k = $account->kistiDetail;
    $today = today()->toDateString();
@endphp
<x-layouts.app :title="$account->account_no">
    <x-page-header :title="'কিস্তি অ্যাকাউন্ট ' . $account->account_no"
                   :description="$account->member->name . ' — ' . $k->frequency->label() . ' কিস্তি'">
        <x-slot:actions>
            @can('collect', $account)
                <a href="{{ route((auth()->user()->isAdmin() ? 'admin' : 'collector') . '.collections.create', $account) }}" class="btn-primary">টাকা নিন</a>
            @endcan
            <a href="{{ route(auth()->user()->isAdmin() ? 'admin.members.show' : 'collector.members.show', $account->member) }}" class="btn-quiet">সদস্যের তথ্য</a>
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
            @can('cancel', $account)
                @if ($account->isActive() && \App\Support\Money::toPaisa($summary['paid']) === 0)
                    <x-modal title="ঋণ বাতিল" :open="$errors->cancel->any()">
                        <x-slot:trigger><button type="button" class="btn-quiet text-danger">ঋণ বাতিল</button></x-slot:trigger>
                        <form method="POST" action="{{ route('admin.kisti.cancel', $account) }}" class="space-y-4">
                            @csrf
                            <p class="text-[15px] text-ink-soft">ভুল করে খোলা ঋণের জন্য। কোনো টাকা জমা না হওয়া পর্যন্ত বাতিল করা যায়। অ্যাকাউন্টটি মুছে যাবে না, "বাতিল" হিসেবে থাকবে।</p>
                            <x-field name="reason" label="বাতিলের কারণ" bag="cancel" required />
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-quiet" @click="open = false">ফিরে যান</button>
                                <button type="submit" class="btn-danger">ঋণ বাতিল করুন</button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($account->isActive())
        <div class="mb-6 rounded-md border border-rule bg-paper px-4 py-3 text-[15px] text-ink-soft">
            এই অ্যাকাউন্টের অবস্থা: <span class="font-semibold">{{ $account->status->label() }}</span>{{ $account->closed_at ? ' (' . bn_date($account->closed_at) . ')' : '' }}।
        </div>
    @endunless

    <section class="panel mb-6 grid grid-cols-2 lg:grid-cols-6 lg:divide-x lg:divide-rule">
        <x-stat label="ঋণের পরিমাণ" :value="bn_money($k->loan_amount)" :note="'সার্ভিস চার্জ ' . bn_money($k->service_charge)" />
        <x-stat label="মোট পরিশোধযোগ্য" :value="bn_money($k->total_payable)" />
        <x-stat label="আদায়" :value="bn_money($summary['paid'])" :note="bn_digits($summary['paid_count']) . '/' . bn_digits($k->total_installments) . 'টি কিস্তি'" />
        <x-stat label="অবশিষ্ট" :value="bn_money($summary['remaining'])" />
        <x-stat label="বকেয়া" :value="bn_money($summary['overdue'])" :note="bn_digits($summary['overdue_count']) . 'টি কিস্তি'" />
        <x-stat label="অগ্রিম জমা" :value="bn_money($summary['advance'])" />
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="panel h-fit p-5">
            <h2 class="mb-3 text-lg font-bold text-ledger-900">ঋণের শর্ত</h2>
            <dl class="space-y-2 text-[15px]">
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">কিস্তির ধরন</dt><dd>{{ $k->frequency->label() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">প্রতি কিস্তি</dt><dd>{{ bn_money($k->installment_amount) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">মোট কিস্তি</dt><dd>{{ bn_digits($k->total_installments) }}টি</dd></div>
                @if (\App\Support\Money::compare((string) $k->last_installment_amount, (string) $k->installment_amount) !== 0)
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">শেষ কিস্তি</dt><dd>{{ bn_money($k->last_installment_amount) }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">কিস্তি শুরুর তারিখ</dt><dd>{{ bn_date($k->start_date) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">প্রথম কিস্তি</dt><dd>{{ bn_date($summary['rows']->first()?->due_date) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">শেষ কিস্তি</dt><dd>{{ bn_date($summary['rows']->last()?->due_date) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">পরবর্তী কিস্তি</dt><dd>{{ $summary['next'] ? bn_date($summary['next']->due_date) : '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">কালেক্টর</dt><dd>{{ $account->collector?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">যিনি খুলেছেন</dt><dd>{{ $account->creator?->name }}</dd></div>
            </dl>
            @if ($account->notes)<p class="mt-3 whitespace-pre-line text-sm text-ink-soft">{{ $account->notes }}</p>@endif
        </section>

        <section class="panel overflow-hidden lg:col-span-2">
            <h2 class="border-b border-rule px-4 py-3 text-lg font-bold text-ledger-900">কিস্তির তালিকা</h2>
            <div class="max-h-[32rem] overflow-auto">
                <table class="register">
                    <thead class="sticky top-0">
                    <tr><th>নং</th><th>তারিখ</th><th class="num">নির্ধারিত</th><th class="num">জমা</th><th class="num">বাকি</th><th>অবস্থা</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($summary['rows'] as $r)
                        @php
                            $date = $r->due_date->toDateString();
                            $left = \App\Support\Money::sub((string) $r->amount_due, (string) $r->amount_paid);
                            $overdue = $date <= $today && \App\Support\Money::toPaisa($left) > 0;
                        @endphp
                        <tr @class(['bg-ledger-50' => $date === $today])>
                            <td>{{ bn_digits($r->sequence_no) }}</td>
                            <td class="whitespace-nowrap">{{ bn_date($r->due_date) }} <span class="text-xs text-ink-soft">{{ \App\Support\BanglaFormatter::DAYS[$r->due_date->dayOfWeek] }}</span></td>
                            <td class="num">{{ bn_money($r->amount_due) }}</td>
                            <td class="num">{{ bn_money($r->amount_paid) }}</td>
                            <td class="num">{{ bn_money($left) }}</td>
                            <td>
                                @if ($r->status === \App\Enums\InstallmentStatus::Paid)
                                    <x-status-badge tone="success">পরিশোধিত</x-status-badge>
                                @elseif ($overdue)
                                    <x-status-badge tone="danger">{{ $r->status === \App\Enums\InstallmentStatus::Partial ? 'আংশিক বকেয়া' : 'বকেয়া' }}</x-status-badge>
                                @elseif ($r->status === \App\Enums\InstallmentStatus::Partial)
                                    <x-status-badge tone="pending">আংশিক</x-status-badge>
                                @else
                                    <x-status-badge>আসন্ন</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    @include('transactions.partials.recent')
</x-layouts.app>
