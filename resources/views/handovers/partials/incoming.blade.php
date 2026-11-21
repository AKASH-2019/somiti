{{-- Pending handovers waiting for the current user's confirmation. $incoming, $rp --}}
@if ($incoming->isNotEmpty())
    <section class="mb-6 space-y-3">
        @foreach ($incoming as $h)
            <div class="panel flex flex-wrap items-center justify-between gap-4 border-pending/40 bg-pending-50/60 p-4">
                <div>
                    <p class="text-[17px]"><span class="font-semibold">{{ $h->sender->name }}</span> আপনার কাছে <span class="font-bold">{{ bn_money($h->amount) }}</span> টাকা হস্তান্তরের অনুরোধ পাঠিয়েছেন।</p>
                    <p class="text-sm text-ink-soft">নম্বর {{ $h->handover_number }} · {{ bn_datetime($h->handover_datetime) }}{{ $h->notes ? ' · ' . $h->notes : '' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-confirm :action="route($rp . '.confirm', $h)" method="POST" tone="primary"
                               title="টাকা গ্রহণ নিশ্চিত করবেন?"
                               :message="'আপনি কি ' . $h->sender->name . '-এর কাছ থেকে ' . bn_money($h->amount) . ' হাতে পেয়েছেন? নিশ্চিত করলে টাকা আপনার ক্যাশে যোগ হবে এবং এটি আর বাতিল করা যাবে না।'"
                               confirm-label="হ্যাঁ, টাকা পেয়েছি" class="btn-primary">টাকা গ্রহণ নিশ্চিত করুন</x-confirm>
                    <a href="{{ route($rp . '.show', $h) }}" class="btn-quiet">গ্রহণ প্রত্যাখ্যান করুন</a>
                </div>
            </div>
        @endforeach
    </section>
@endif
