<x-layouts.app title="নতুন সদস্য">
    <x-page-header title="নতুন সদস্য" description="সদস্য আইডি স্বয়ংক্রিয়ভাবে তৈরি হবে।" />
    <form method="POST" action="{{ route('admin.members.store') }}" class="panel max-w-3xl p-5">
        @csrf
        @include('members.partials.form', ['collectors' => $collectors])
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('admin.members.index') }}" class="btn-quiet">বাতিল</a>
            <button type="submit" class="btn-primary">সংরক্ষণ করুন</button>
        </div>
    </form>
</x-layouts.app>
