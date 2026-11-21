<x-layouts.app title="পাসওয়ার্ড পরিবর্তন">
    <x-page-header title="পাসওয়ার্ড পরিবর্তন" description="নতুন পাসওয়ার্ড সেট হলে অন্য সব ডিভাইস থেকে লগআউট হয়ে যাবে।" />
    <form method="POST" action="{{ route('password.change') }}" class="panel max-w-md space-y-4 p-5">
        @csrf
        @method('PUT')
        <x-field name="current_password" label="বর্তমান পাসওয়ার্ড" type="password" required autocomplete="current-password" />
        <x-field name="password" label="নতুন পাসওয়ার্ড" type="password" required autocomplete="new-password" help="কমপক্ষে ৮ অক্ষর" />
        <x-field name="password_confirmation" label="নতুন পাসওয়ার্ড আবার লিখুন" type="password" required autocomplete="new-password" />
        <div class="flex justify-end">
            <button type="submit" class="btn-primary">পাসওয়ার্ড পরিবর্তন করুন</button>
        </div>
    </form>
</x-layouts.app>
