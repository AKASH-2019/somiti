<x-layouts.app title="নতুন সদস্য">
    <x-page-header title="নতুন সদস্য" description="নতুন সদস্য স্বয়ংক্রিয়ভাবে আপনার দায়িত্বে যুক্ত হবে।" />
    <form method="POST" action="{{ route('collector.members.store') }}" class="panel max-w-3xl p-5">
        @csrf
        @include('members.partials.form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('collector.members.index') }}" class="btn-quiet">বাতিল</a>
            <button type="submit" class="btn-primary">সংরক্ষণ করুন</button>
        </div>
    </form>
</x-layouts.app>
