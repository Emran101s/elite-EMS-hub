<x-layouts.app title="Team" subtitle="The people running your operations.">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($members as $member)
            <div class="card flex items-center gap-4 p-6">
                <x-user-avatar :user="$member" size="h-12 w-12" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-navy-900">{{ $member->name }}</p>
                    <p class="mt-0.5 truncate text-xs font-medium text-gold-700">{{ $member->title ?? 'Team Member' }}</p>
                    <p class="mt-0.5 truncate text-xs text-muted">{{ $member->email }}</p>
                </div>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-sm text-muted">No team members yet.</p>
        @endforelse
    </div>
</x-layouts.app>
