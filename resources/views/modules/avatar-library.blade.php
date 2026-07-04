<x-layouts.app title="Event Avatar Library" subtitle="Premium visual identities — every event gets a digital twin, never a generic icon.">

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach (['all' => 'All', ...collect(\App\Models\EventAvatar::CATEGORIES)->mapWithKeys(fn ($c) => [$c => str($c)->title()])] as $value => $label)
                <a href="{{ route('events.avatars', $value === 'all' ? [] : ['category' => $value]) }}"
                   @class([
                       'rounded-full px-3.5 py-1.5 text-xs font-semibold transition',
                       'bg-navy-900 text-gold-400' => $category === $value || ($value === 'all' && ! $category),
                       'bg-white text-navy-600 ring-1 ring-line hover:text-navy-900' => ! ($category === $value || ($value === 'all' && ! $category)),
                   ])>{{ $label }}</a>
            @endforeach
        </div>
        <a href="{{ route('events.create') }}" class="btn-gold text-xs">+ New Event</a>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($avatars as $avatar)
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-page/60 p-4">
                    <x-event-avatar :avatar="$avatar" :ring="false" size="xl" class="block [&>span]:h-40 [&>span]:w-full" />
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-navy-900">{{ $avatar->name }}</p>
                            <p class="text-xs text-muted">{{ $avatar->subtitle }}</p>
                        </div>
                        <span class="rounded-full bg-navy-50 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-navy-600">
                            {{ str($avatar->category)->title() }}
                        </span>
                    </div>

                    <p class="mt-3 text-xs text-muted"><span class="font-semibold text-navy-800">Best for:</span> {{ $avatar->best_for }}</p>

                    <div class="mt-4 flex items-center justify-between border-t border-line pt-4">
                        <div class="flex gap-1.5">
                            @foreach ($avatar->colors as $color)
                                <span class="h-4 w-4 rounded-full ring-1 ring-line" style="background: {{ $color }}"></span>
                            @endforeach
                        </div>
                        <a href="{{ route('events.create', ['avatar' => $avatar->id]) }}"
                           class="text-xs font-semibold text-gold-600 hover:text-gold-700">Use for new event →</a>
                    </div>
                </div>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-sm text-muted">No avatars in this category yet.</p>
        @endforelse
    </div>

    <p class="mt-6 text-xs text-muted">
        {{ $avatars->count() }} of {{ \App\Models\EventAvatar::active()->count() }} avatars ·
        Admin uploads and 3D islands are on the roadmap — see docs/command-center/avatar-library.md
    </p>
</x-layouts.app>
