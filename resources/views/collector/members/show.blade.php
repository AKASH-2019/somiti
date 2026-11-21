<x-layouts.app :title="$member->name">
    <x-page-header :title="$member->name" :description="$member->business_name">
        <x-slot:actions>
            <a href="{{ route('collector.members.edit', $member) }}" class="btn-quiet">সম্পাদনা</a>
        </x-slot:actions>
    </x-page-header>
    <div class="max-w-3xl">
        @include('members.partials.details')
            @include('members.partials.accounts')
    </div>
</x-layouts.app>
