{{-- Read-only member card used on admin and collector detail pages. --}}
<section class="panel overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rule px-5 py-3">
        <div>
            <p class="text-sm text-ink-soft">সদস্য আইডি</p>
            <p class="text-lg font-bold text-ledger-900">{{ $member->member_code }}</p>
        </div>
        <x-status-badge :tone="$member->status->tone()">{{ $member->status->label() }}</x-status-badge>
    </div>
    <dl class="grid gap-x-6 gap-y-4 px-5 py-4 text-[15px] sm:grid-cols-2">
        <div><dt class="text-sm text-ink-soft">সদস্যের নাম</dt><dd class="font-semibold">{{ $member->name }}</dd></div>
        <div><dt class="text-sm text-ink-soft">মোবাইল নম্বর</dt><dd><a href="tel:{{ $member->mobile }}" class="font-semibold text-ledger-700 hover:underline">{{ bn_digits($member->mobile) }}</a></dd></div>
        <div><dt class="text-sm text-ink-soft">প্রতিষ্ঠানের নাম</dt><dd>{{ $member->business_name ?: '—' }}</dd></div>
        <div><dt class="text-sm text-ink-soft">প্রতিষ্ঠানের ঠিকানা</dt><dd>{{ $member->business_address ?: '—' }}</dd></div>
        <div><dt class="text-sm text-ink-soft">যোগদানের তারিখ</dt><dd>{{ bn_date($member->joined_at) }}</dd></div>
        <div><dt class="text-sm text-ink-soft">দায়িত্বপ্রাপ্ত কালেক্টর</dt><dd>{{ $member->collector?->name ?? '—' }}</dd></div>
        <div><dt class="text-sm text-ink-soft">যিনি যোগ করেছেন</dt><dd>{{ $member->creator?->name }} ({{ bn_date($member->created_at) }})</dd></div>
        @if ($member->notes)
            <div class="sm:col-span-2"><dt class="text-sm text-ink-soft">মন্তব্য</dt><dd class="whitespace-pre-line">{{ $member->notes }}</dd></div>
        @endif
    </dl>
</section>
