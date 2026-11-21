<x-layouts.app title="টাকা গ্রহণ নিশ্চিতকরণ">
    <x-page-header title="টাকা গ্রহণ নিশ্চিতকরণ" description="টাকা হাতে পেলে তবেই নিশ্চিত করুন" />

    @if ($incoming->isEmpty())
        <div class="panel mb-6 p-5 text-ink-soft">আপনার কাছে কোনো অপেক্ষমাণ হস্তান্তর নেই।</div>
    @else
        @include('handovers.partials.incoming')
    @endif

    @if ($recent->isNotEmpty())
        <h2 class="mb-3 text-lg font-bold text-ledger-900">সাম্প্রতিক</h2>
        <div class="panel overflow-hidden">
            @include('handovers.partials.table', ['handovers' => $recent->each(fn ($h) => $h->setRelation('receiver', auth()->user()))])
        </div>
    @endif
</x-layouts.app>
