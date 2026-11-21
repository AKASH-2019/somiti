<x-layouts.app title="বিজ্ঞপ্তি">
    <x-page-header title="বিজ্ঞপ্তি">
        <x-slot:actions>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit" class="btn-quiet">সব পড়া হয়েছে</button></form>
            @endif
        </x-slot:actions>
    </x-page-header>
    <div class="panel max-w-3xl overflow-hidden">
        @forelse ($notifications as $n)
            <a href="{{ route('notifications.open', $n->id) }}" @class([
                'flex items-start justify-between gap-4 border-b border-rule px-5 py-3 last:border-b-0 hover:bg-ledger-50',
                'bg-pending-50/50 font-semibold' => ! $n->read_at,
            ])>
                <span>{{ $n->data['message'] ?? '' }}</span>
                <span class="whitespace-nowrap text-sm font-normal text-ink-soft">{{ bn_datetime($n->created_at) }}</span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-ink-soft">কোনো বিজ্ঞপ্তি নেই।</p>
        @endforelse
    </div>
    {{ $notifications->links() }}
</x-layouts.app>
