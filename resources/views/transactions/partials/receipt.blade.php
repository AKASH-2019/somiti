{{-- The receipt itself. $tx, $covered, $balance, $compact --}}
@php
    $account = $tx->account;
    $isSomiti = $account->type === \App\Enums\AccountType::Somiti;
    $row = $compact ? 'flex justify-between gap-3 py-0.5' : 'grid grid-cols-[11rem_1fr] gap-3 border-b border-rule py-1.5';
@endphp
<div @class([
    'receipt relative bg-white text-ink',
    'mx-auto w-[80mm] p-3 text-[13px] leading-snug' => $compact,
    'mx-auto max-w-[190mm] border border-rule p-8 text-[15px]' => ! $compact,
])>
    @if ($tx->isReversed())
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <span class="-rotate-12 rounded border-4 border-danger px-4 py-1 text-4xl font-bold text-danger/70">বাতিলকৃত</span>
        </div>
    @endif

    <header @class(['text-center', 'mb-2 border-b border-dashed border-ink-soft pb-2' => $compact, 'mb-5 border-b-4 border-double border-rule pb-4' => ! $compact])>
        <p @class(['font-bold text-ledger-900', 'text-base' => $compact, 'text-2xl' => ! $compact])>{{ setting('org_name') }}</p>
        @if (setting('org_address'))<p class="text-ink-soft">{{ setting('org_address') }}</p>@endif
        @if (setting('org_mobile'))<p class="text-ink-soft">মোবাইল: {{ bn_digits(setting('org_mobile')) }}</p>@endif
        <p @class(['mt-1 font-semibold', 'text-lg' => ! $compact])>টাকা জমার রসিদ{{ $tx->entry_type === \App\Enums\EntryType::Historical ? ' (পুরনো খাতার এন্ট্রি)' : '' }}{{ ($settlement ?? null) ? ' — সেটেলমেন্ট' : '' }}</p>
    </header>

    <dl>
        <div class="{{ $row }}"><dt class="text-ink-soft">রসিদ নম্বর</dt><dd class="font-semibold">{{ $tx->receipt_no }}</dd></div>
        <div class="{{ $row }}"><dt class="text-ink-soft">সদস্যের নাম</dt><dd class="font-semibold">{{ $tx->member->name }}</dd></div>
        <div class="{{ $row }}"><dt class="text-ink-soft">সদস্য আইডি</dt><dd>{{ $tx->member->member_code }}</dd></div>
        <div class="{{ $row }}"><dt class="text-ink-soft">অ্যাকাউন্ট নম্বর</dt><dd>{{ $account->account_no }} ({{ $account->type->label() }})</dd></div>
        @if ($isSomiti)
            <div class="{{ $row }}"><dt class="text-ink-soft">শেয়ারের সংখ্যা</dt><dd>{{ bn_digits($account->somitiDetail->number_of_shares) }}</dd></div>
            <div class="{{ $row }}"><dt class="text-ink-soft">দৈনিক জমার পরিমাণ</dt><dd>{{ bn_money($account->somitiDetail->daily_deposit_amount) }}</dd></div>
        @else
            <div class="{{ $row }}"><dt class="text-ink-soft">কিস্তি</dt><dd>{{ $account->kistiDetail->frequency->label() }} {{ bn_money($account->kistiDetail->installment_amount) }}</dd></div>
        @endif
        <div class="{{ $row }}"><dt class="text-ink-soft">প্রকৃত জমার তারিখ</dt><dd>{{ bn_datetime($tx->collected_at) }}</dd></div>
        <div class="{{ $row }}">
            <dt class="text-ink-soft">{{ $isSomiti ? 'যে তারিখের জন্য জমা' : 'যে কিস্তির জন্য জমা' }}</dt>
            <dd>@foreach ($covered as $line)<span class="block">{{ $line }}</span>@endforeach</dd>
        </div>
    </dl>

    <div @class(['my-3 text-center', 'border-y border-dashed border-ink-soft py-2' => $compact, 'rounded-md bg-ledger-50 py-4' => ! $compact])>
        <p class="text-ink-soft">মোট জমার পরিমাণ</p>
        <p @class(['font-bold tabular-nums text-ledger-900', 'text-xl' => $compact, 'text-3xl' => ! $compact])>{{ bn_money($tx->amount) }}</p>
        <p class="mt-1">কথায়: {{ bn_words($tx->amount) }}</p>
    </div>

    <dl>
        @if ($settlement ?? null)
            <div class="{{ $row }}"><dt class="text-ink-soft">সেটেলমেন্টের আগে বাকি</dt><dd>{{ bn_money($settlement->outstanding_before) }}</dd></div>
            <div class="{{ $row }}"><dt class="text-ink-soft">মওকুফ</dt><dd class="font-semibold">{{ bn_money($settlement->waived_amount) }}</dd></div>
        @endif
        <div class="{{ $row }}"><dt class="text-ink-soft">{{ $balance['label'] }}</dt><dd class="font-semibold">{{ bn_money($balance['value']) }}</dd></div>
        <div class="{{ $row }}"><dt class="text-ink-soft">পেমেন্ট পদ্ধতি</dt><dd>{{ $tx->payment_method->label() }}</dd></div>
        <div class="{{ $row }}"><dt class="text-ink-soft">কালেক্টরের নাম</dt><dd>{{ $tx->collector->name }}</dd></div>
        @if ($tx->notes)
            <div class="{{ $row }}"><dt class="text-ink-soft">মন্তব্য</dt><dd>{{ $tx->notes }}</dd></div>
        @endif
        @if ($tx->isReversed())
            <div class="{{ $row }} text-danger"><dt>বাতিলের কারণ</dt><dd>{{ $tx->reversal_reason }} — {{ $tx->reverser?->name }}, {{ bn_datetime($tx->reversed_at) }}</dd></div>
        @endif
    </dl>

    @unless ($compact)
        <div class="mt-12 grid grid-cols-2 gap-10 text-center text-ink-soft">
            <p class="border-t border-ink-soft pt-1">সদস্যের স্বাক্ষর</p>
            <p class="border-t border-ink-soft pt-1">কালেক্টরের স্বাক্ষর</p>
        </div>
    @endunless
    @if (setting('receipt_footer'))
        <p @class(['text-center text-ink-soft', 'mt-2 border-t border-dashed border-ink-soft pt-2' => $compact, 'mt-6' => ! $compact])>{{ setting('receipt_footer') }}</p>
    @endif
</div>
