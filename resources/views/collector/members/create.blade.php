<x-layouts.app title="নতুন সদস্য">
    <x-page-header title="নতুন সদস্য" description="সদস্যের তথ্য এবং চাইলে একসাথে সমিতি অ্যাকাউন্ট।" />
    <form method="POST" action="{{ route('collector.members.store') }}" class="max-w-3xl">
        @csrf
        <section class="panel p-5">
            <h2 class="mb-4 text-lg font-bold text-ledger-900">সদস্যের তথ্য</h2>
            @include('members.partials.form', ['suggestedCode' => $suggestedCode])
        </section>

        @include('members.partials.somiti-section')

        <div class="mt-6 flex flex-wrap justify-end gap-2">
            <a href="{{ route('collector.members.index') }}" class="btn-quiet">বাতিল</a>
            <button type="submit" class="btn-primary">সংরক্ষণ করুন</button>
        </div>
        <p class="mt-3 text-right text-sm text-ink-soft">কিস্তি (ঋণ) দিতে হলে সংরক্ষণের পর সদস্যের পাতা থেকে "নতুন কিস্তি অ্যাকাউন্ট" খুলুন।</p>
    </form>
</x-layouts.app>
