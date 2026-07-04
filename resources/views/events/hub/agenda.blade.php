@php $accent = $event->theme()['accent']; @endphp

@forelse ($event->agendaDays as $day)
    <div class="card mb-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <div>
                <h3 class="text-sm font-bold text-navy-900">{{ $day->label }}</h3>
                <p class="text-xs text-muted">{{ $day->date->format('l, F j, Y') }} · {{ $day->sessions->count() }} sessions</p>
            </div>
            <span class="rounded-full bg-navy-50 px-3 py-1 text-xs font-semibold text-navy-600">Day {{ $loop->iteration }}</span>
        </div>
        <div class="divide-y divide-line">
            @foreach ($day->sessions as $session)
                <div class="flex items-center gap-4 px-6 py-3.5">
                    <div class="w-24 shrink-0 border-l-2 pl-3" style="border-color: {{ $accent }}">
                        <p class="text-xs font-bold text-navy-900">{{ substr($session->starts_at, 0, 5) }}</p>
                        <p class="text-[0.65rem] text-muted">{{ substr($session->ends_at, 0, 5) }}</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-navy-900">{{ $session->title }}</p>
                        <p class="mt-0.5 text-xs text-muted">
                            {{ $session->room?->name ?? 'No room' }}
                            @if ($session->speaker) · 🎤 {{ $session->speaker }} @endif
                            @if ($session->moderator) · Moderator: {{ $session->moderator }} @endif
                            @if ($session->track) · {{ $session->track }} @endif
                        </p>
                    </div>
                    <span class="hidden rounded-full bg-navy-50 px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide text-navy-600 sm:inline">
                        {{ str($session->type)->replace('_', ' ')->title() }}
                    </span>
                    <x-status-badge :status="$session->status" class="hidden sm:inline-flex" />
                </div>
            @endforeach
        </div>
    </div>
@empty
    <div class="card flex flex-col items-center px-8 py-16 text-center">
        <h3 class="text-sm font-bold text-navy-900">No agenda yet</h3>
        <p class="mt-1 max-w-md text-xs text-muted">Add days and sessions to build the program. Drag-and-drop builder, Excel import and PDF export arrive in the next iteration.</p>
    </div>
@endforelse

@if ($event->agendaDays->isNotEmpty())
    <p class="text-xs text-muted">Coming next: drag-and-drop between rooms, duplicate day, Excel import, PDF export, Day/Room/Speaker/Track views.</p>
@endif
