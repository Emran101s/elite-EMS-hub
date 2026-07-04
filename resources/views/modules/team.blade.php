<x-layouts.app title="Team" subtitle="The people running your operations.">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($members as $member)
            <div class="card flex items-center gap-4 p-6">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-sm font-bold text-gold-400">
                    {{ $member->initials() }}
                </span>
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
