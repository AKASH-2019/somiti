<x-layouts.app :title="'হস্তান্তর ' . $h->handover_number">
    <x-page-header :title="'হস্তান্তর ' . $h->handover_number" :description="$h->sender->name . ' → ' . $h->receiver->name">
        <x-slot:actions>
            <a href="{{ route($rp . '.index') }}" class="btn-quiet">সব হস্তান্তর</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="panel overflow-hidden lg:col-span-3">
            <div class="flex items-center justify-between border-b border-rule px-5 py-4">
                <p class="text-3xl font-bold tabular-nums text-ledger-900">{{ bn_money($h->amount) }}</p>
                <x-status-badge :tone="$h->status->tone()" class="text-base">{{ $h->status->label() }}</x-status-badge>
            </div>
            <dl class="grid gap-x-6 gap-y-3 px-5 py-4 text-[15px] sm:grid-cols-2">
                <div><dt class="text-sm text-ink-soft">প্রেরক কালেক্টর</dt><dd class="font-semibold">{{ $h->sender->name }}</dd></div>
                <div><dt class="text-sm text-ink-soft">প্রাপক</dt><dd class="font-semibold">{{ $h->receiver->name }}</dd></div>
                <div><dt class="text-sm text-ink-soft">হস্তান্তরের তারিখ</dt><dd>{{ bn_datetime($h->handover_datetime) }}</dd></div>
                <div><dt class="text-sm text-ink-soft">হস্তান্তর নম্বর</dt><dd>{{ $h->handover_number }}</dd></div>
                @if ($h->notes)<div class="sm:col-span-2"><dt class="text-sm text-ink-soft">মন্তব্য</dt><dd>{{ $h->notes }}</dd></div>@endif
            </dl>

            <ol class="border-t border-rule px-5 py-4 text-[15px]">
                <li class="mb-2">অনুরোধ পাঠানো হয়েছে — {{ bn_datetime($h->created_at) }}</li>
                @if ($h->received_datetime)<li class="mb-2 text-ledger-700">গ্রহণ নিশ্চিত — {{ bn_datetime($h->received_datetime) }} ({{ $h->confirmer?->name }})</li>@endif
                @if ($h->rejection_reason)<li class="mb-2 text-danger">প্রত্যাখ্যানের কারণ: {{ $h->rejection_reason }}</li>@endif
                @if ($h->cancelled_at)<li class="mb-2 text-ink-soft">প্রেরক বাতিল করেছেন — {{ bn_datetime($h->cancelled_at) }}</li>@endif
                @if ($h->disputed_at)<li class="mb-2 text-danger">বিরোধ — {{ $h->disputer?->name }}, {{ bn_datetime($h->disputed_at) }}: {{ $h->dispute_reason }}</li>@endif
                @if ($h->resolved_at)<li class="mb-2">এডমিনের নিষ্পত্তি — {{ $h->resolver?->name }}, {{ bn_datetime($h->resolved_at) }}: {{ $h->resolution_note }}</li>@endif
            </ol>
        </section>

        <section class="panel h-fit space-y-3 p-5 lg:col-span-2">
            <h2 class="text-lg font-bold text-ledger-900">করণীয়</h2>
            @php($none = true)

            @can('confirm', $h)
                @php($none = false)
                <x-confirm :action="route($rp . '.confirm', $h)" method="POST" tone="primary" title="টাকা গ্রহণ নিশ্চিত করবেন?"
                           :message="'আপনি কি ' . $h->sender->name . '-এর কাছ থেকে ' . bn_money($h->amount) . ' হাতে পেয়েছেন? নিশ্চিত করার পর আর বাতিল করা যাবে না।'"
                           confirm-label="হ্যাঁ, টাকা পেয়েছি" class="btn-primary w-full">টাকা গ্রহণ নিশ্চিত করুন</x-confirm>
                @error('reason', 'confirm') <p class="field-error">{{ $message }}</p> @enderror
            @endcan

            @can('reject', $h)
                <x-modal title="গ্রহণ প্রত্যাখ্যান" :open="$errors->reject->any()">
                    <x-slot:trigger><button type="button" class="btn-quiet w-full">গ্রহণ প্রত্যাখ্যান করুন</button></x-slot:trigger>
                    <form method="POST" action="{{ route($rp . '.reject', $h) }}" class="space-y-4">
                        @csrf
                        <p class="text-[15px] text-ink-soft">টাকা হাতে না পেলে প্রত্যাখ্যান করুন। কোনো টাকা স্থানান্তর হবে না।</p>
                        <x-field name="reason" label="কারণ" bag="reject" required placeholder="যেমন: টাকা পাইনি / কম পেয়েছি" />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-quiet" @click="open = false">ফিরে যান</button>
                            <button type="submit" class="btn-danger">প্রত্যাখ্যান করুন</button>
                        </div>
                    </form>
                </x-modal>
            @endcan

            @can('cancel', $h)
                @php($none = false)
                <x-confirm :action="route($rp . '.cancel', $h)" method="POST" title="হস্তান্তর অনুরোধ বাতিল করবেন?"
                           message="প্রাপক এখনো নিশ্চিত করেননি। বাতিল করলে টাকা আপনার কাছেই থাকবে।"
                           confirm-label="হ্যাঁ, বাতিল করুন" class="btn-quiet w-full">অনুরোধ বাতিল করুন</x-confirm>
            @endcan

            @can('dispute', $h)
                @php($none = false)
                <x-modal title="বিরোধ জানান" :open="$errors->dispute->any()">
                    <x-slot:trigger><button type="button" class="btn-quiet w-full text-danger">বিরোধ জানান</button></x-slot:trigger>
                    <form method="POST" action="{{ route($rp . '.dispute', $h) }}" class="space-y-4">
                        @csrf
                        <p class="text-[15px] text-ink-soft">টাকা দেওয়া-নেওয়া নিয়ে মতভেদ হলে এডমিনকে জানান। এডমিন তদন্ত করে সিদ্ধান্ত দেবেন; ততক্ষণ টাকা প্রেরকের হিসাবে আটকে থাকবে।</p>
                        <x-field name="reason" label="কী হয়েছে" bag="dispute" required />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-quiet" @click="open = false">ফিরে যান</button>
                            <button type="submit" class="btn-danger">বিরোধ জানান</button>
                        </div>
                    </form>
                </x-modal>
            @endcan

            @can('resolve', $h)
                @php($none = false)
                <form method="POST" action="{{ route('admin.handovers.resolve', $h) }}" class="space-y-3 rounded-md border border-danger/30 p-3">
                    @csrf
                    <p class="font-semibold">বিরোধ নিষ্পত্তি</p>
                    <label class="flex items-center gap-2"><input type="radio" name="outcome" value="received" required> গৃহীত — টাকা প্রাপকের কাছে গেছে</label>
                    <label class="flex items-center gap-2"><input type="radio" name="outcome" value="rejected"> প্রত্যাখ্যাত — টাকা প্রেরকের কাছেই আছে</label>
                    @error('outcome', 'resolve') <p class="field-error">{{ $message }}</p> @enderror
                    <x-field name="note" label="তদন্তের মন্তব্য" bag="resolve" required />
                    @error('reason', 'resolve') <p class="field-error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn-primary w-full">সিদ্ধান্ত সংরক্ষণ করুন</button>
                </form>
            @endcan

            @if ($none && ! auth()->user()->can('reject', $h))
                <p class="text-ink-soft">
                    @switch($h->status)
                        @case(\App\Enums\HandoverStatus::Received) এই হস্তান্তর সম্পন্ন। সম্পন্ন হস্তান্তর বাতিল বা মুছে ফেলা যায় না। @break
                        @case(\App\Enums\HandoverStatus::Pending) প্রাপকের নিশ্চিতকরণের অপেক্ষায়। @break
                        @case(\App\Enums\HandoverStatus::Disputed) এডমিনের সিদ্ধান্তের অপেক্ষায়। @break
                        @default কোনো করণীয় নেই।
                    @endswitch
                </p>
            @endif
        </section>
    </div>
</x-layouts.app>
