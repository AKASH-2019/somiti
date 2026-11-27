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
            @can('settle', $account)
                @if (\App\Support\Money::toPaisa($summary['remaining']) > 0)
                    <x-modal title="সেটেলমেন্ট — ঋণ বন্ধ করুন" :open="$errors->settle->any()">
                        <x-slot:trigger><button type="button" class="btn-quiet">সেটেলমেন্ট</button></x-slot:trigger>
                        <form method="POST" action="{{ route('admin.kisti.settle', $account) }}" class="space-y-4"
                              x-data="{ amount: @js(old('amount', '')), left: {{ (float) $summary['remaining'] }} }">
                            @csrf
                            <p class="text-[15px] text-ink-soft">একবারে কম টাকা নিয়ে ঋণটি বন্ধ করুন। বাকি টাকা <b>মওকুফ</b> হিসেবে লেখা থাকবে। ভুল হলে রসিদ থেকে "লেনদেন বাতিল" করলে আগের অবস্থায় ফিরবে।</p>
                            <div class="rounded-md bg-ledger-50 px-4 py-3 text-[15px]">
                                বাকি ঋণ: <span class="font-bold">{{ bn_money($summary['remaining']) }}</span>
                                @if (\App\Support\Money::toPaisa($summary['overdue']) > 0)<span class="text-sm text-ink-soft">(বকেয়া {{ bn_money($summary['overdue']) }})</span>@endif
                            </div>
                            <div>
                                <label for="settle_amount" class="field-label">এখন যত টাকা নেওয়া হচ্ছে (৳)</label>
                                <input id="settle_amount" name="amount" type="number" step="0.01" min="0.01" :max="left" class="field text-xl font-semibold" x-model="amount" required>
                                <p class="field-help" x-show="parseFloat(amount) > 0 && parseFloat(amount) <= left">
                                    মওকুফ হবে: <span class="font-semibold text-pending" x-text="bnMoney(Math.round((left - parseFloat(amount)) * 100) / 100)"></span>
                                </p>
                                <p class="field-error" x-show="parseFloat(amount) > left" x-cloak>বাকি ঋণের চেয়ে বেশি।</p>
                                @error('amount', 'settle') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="receiver_id" class="field-label">টাকা কার হাতে</label>
                                <select id="receiver_id" name="receiver_id" class="field" required>
                                    @foreach ($receivers as $r)
                                        <option value="{{ $r->id }}" @selected(old('receiver_id') == $r->id)>{{ $r->name }}{{ $r->isAdmin() ? ' (এডমিন)' : ' (কালেক্টর)' }}</option>
                                    @endforeach
                                </select>
                                <p class="field-help">যার হাতে নগদ টাকা দেওয়া হলো, তার ক্যাশে যোগ হবে।</p>
                            </div>
                            <x-field name="reason" label="কারণ" bag="settle" required placeholder="যেমন: সদস্য এককালীন পরিশোধ করে ঋণ বন্ধ করেছেন" />
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-quiet" @click="open = false">ফিরে যান</button>
                                <button type="submit" class="btn-primary" :disabled="!(parseFloat(amount) > 0) || parseFloat(amount) > left">সেটেলমেন্ট করুন</button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            @endcan
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

    @if ($settlement)
        <div class="mb-6 rounded-md border border-pending/30 bg-pending-50 px-4 py-3 text-[15px] text-ink">
            <p class="font-semibold">সেটেলমেন্টে বন্ধ — {{ bn_date($account->closed_at) }}</p>
            <p>বাকি ছিল {{ bn_money($settlement->outstanding_before) }} · নেওয়া হয়েছে <b>{{ bn_money($settlement->settled_amount) }}</b> · মওকুফ <b>{{ bn_money($settlement->waived_amount) }}</b></p>
            <p class="text-sm text-ink-soft">কারণ: {{ $settlement->reason }} — {{ $settlement->settler?->name }} ·
                <a href="{{ route((auth()->user()->isAdmin() ? 'admin' : 'collector') . '.transactions.show', $settlement->transaction_id) }}" class="font-semibold text-ledger-700 underline">রসিদ {{ $settlement->transaction?->receipt_no }}</a></p>
        </div>
    @endif

    @unless ($account->isActive() || $settlement)
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
        @if (\App\Support\Money::toPaisa($summary['waived']) > 0)
            <x-stat label="মওকুফ" :value="bn_money($summary['waived'])" note="সেটেলমেন্ট" />
        @else
            <x-stat label="অগ্রিম জমা" :value="bn_money($summary['advance'])" />
        @endif
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
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-rule px-4 py-3">
                <h2 class="text-lg font-bold text-ledger-900">কিস্তির তালিকা</h2>
                <span class="text-sm text-ink-soft">মোট {{ bn_digits($summary['rows']->count()) }}টি · {{ bn_date($summary['rows']->first()?->due_date) }} – {{ bn_date($summary['rows']->last()?->due_date) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="register">
                    <thead>
                    <tr><th>নং</th><th>তারিখ</th><th class="num">নির্ধারিত</th><th class="num">জমা</th><th class="num">বাকি</th><th>অবস্থা</th></tr>
                    </thead>
                    <tbody>
                    @php $lastMonth = null; @endphp
                    @foreach ($summary['rows'] as $r)
                        @php
                            $newMonth = $r->due_date->format('Y-m') !== $lastMonth;
                            $lastMonth = $r->due_date->format('Y-m');
                            $date = $r->due_date->toDateString();
                            $left = \App\Support\Money::sub((string) $r->amount_due, (string) $r->amount_paid);
                            $overdue = $date <= $today && \App\Support\Money::toPaisa($left) > 0;
                        @endphp
                        @if ($newMonth)
                            <tr class="bg-ledger-50"><td colspan="6" class="py-1.5 text-sm font-bold text-ledger-900">{{ \App\Support\BanglaFormatter::MONTHS[$r->due_date->month] }} {{ bn_digits($r->due_date->year) }}</td></tr>
                        @endif
                        <tr @class(['bg-ledger-50' => $date === $today])>
                            <td>{{ bn_digits($r->sequence_no) }}</td>
                            <td class="whitespace-nowrap">{{ bn_date($r->due_date) }} <span class="text-xs text-ink-soft">{{ \App\Support\BanglaFormatter::DAYS[$r->due_date->dayOfWeek] }}</span></td>
                            <td class="num">{{ bn_money($r->amount_due) }}</td>
                            <td class="num">{{ bn_money($r->amount_paid) }}</td>
                            <td class="num">{{ bn_money($left) }}</td>
                            <td>
                                @if ($r->status === \App\Enums\InstallmentStatus::Waived)
                                    <x-status-badge tone="pending">মওকুফ {{ bn_money($r->amount_waived) }}</x-status-badge>
                                @elseif ($r->status === \App\Enums\InstallmentStatus::Paid)
                                    <x-status-badge tone="success">পরিশোধিত</x-status-badge>
                                    @if (\App\Support\Money::toPaisa((string) $r->amount_waived) > 0)<span class="block text-xs text-pending">মওকুফ {{ bn_money($r->amount_waived) }}</span>@endif
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
