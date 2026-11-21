{{-- GET filter bar. Slot = extra filter controls. --}}
@props(['placeholder' => 'নাম, আইডি বা মোবাইল নম্বর দিয়ে খুঁজুন'])
<form method="GET" class="no-print mb-4 flex flex-wrap items-end gap-2">
    <div class="min-w-56 flex-1">
        <label for="q" class="sr-only">খুঁজুন</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="field">
    </div>
    {{ $slot }}
    <button type="submit" class="btn-primary">খুঁজুন</button>
    @if (request()->hasAny(['q', 'status', 'collector']))
        <a href="{{ url()->current() }}" class="btn-quiet">মুছে ফেলুন</a>
    @endif
</form>
