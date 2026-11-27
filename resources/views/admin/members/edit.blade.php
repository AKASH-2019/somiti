<x-layouts.app title="সদস্যের তথ্য সম্পাদনা">
    <x-page-header :title="'সম্পাদনা: ' . $member->name" :description="'সদস্য আইডি ' . $member->member_code" />
    <form method="POST" action="{{ route('admin.members.update', $member) }}" class="panel max-w-3xl p-5">
        @csrf
        @method('PUT')
        @include('members.partials.form', ['member' => $member, 'codeEditable' => true])
        <p class="field-help mt-4">কালেক্টর ও অবস্থা পরিবর্তন সদস্যের বিস্তারিত পাতা থেকে করুন।</p>
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('admin.members.show', $member) }}" class="btn-quiet">বাতিল</a>
            <button type="submit" class="btn-primary">সংরক্ষণ করুন</button>
        </div>
    </form>
</x-layouts.app>
