<x-layouts.app title="কালেক্টরের তথ্য সম্পাদনা">
    <x-page-header :title="'সম্পাদনা: ' . $collector->name" />
    <form method="POST" action="{{ route('admin.collectors.update', $collector) }}" class="panel max-w-2xl space-y-4 p-5">
        @csrf
        @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="কালেক্টরের নাম" :value="$collector->name" required class="sm:col-span-2" />
            <x-field name="username" label="ইউজারনেম" :value="$collector->username" required autocapitalize="none" />
            <x-field name="mobile" label="মোবাইল নম্বর" :value="$collector->mobile" required inputmode="tel" />
        </div>
        <p class="field-help">পাসওয়ার্ড পরিবর্তনের জন্য কালেক্টরের বিস্তারিত পাতায় "পাসওয়ার্ড রিসেট" ব্যবহার করুন।</p>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('admin.collectors.show', $collector) }}" class="btn-quiet">বাতিল</a>
            <button type="submit" class="btn-primary">সংরক্ষণ করুন</button>
        </div>
    </form>
</x-layouts.app>
