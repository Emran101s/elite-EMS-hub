@props([
    'title',
    'module' => null,        // where the task lives, e.g. "Venue"
    'due' => null,           // pre-formatted date string
    'status' => 'in_progress',
    'overdue' => false,
    'flag' => null,          // overrides the derived label
    'progress' => null,      // 0..100
    'assignees' => [],       // ['Alyona Dolgopolova', …]
    'extra' => 0,            // "+2" beyond the shown avatars
    'more' => true,          // the ⋯ button
])
@php
    $tone = \App\Support\Tone::forTask($status, $overdue);
    $label = $flag ?? ($overdue ? 'Overdue' : \Illuminate\Support\Str::of($status)->replace('_', ' ')->title());
    $shown = array_slice($assignees, 0, 2);
@endphp
<article {{ $attributes->merge(['class' => 'o-task']) }}>
    @if ($more)
        <button type="button" class="o-task__more" aria-label="More">
            <x-orbit.icon name="dots" :size="14" />
        </button>
    @endif

    {{-- Overdue is the only thing in this system allowed to pulse. --}}
    <div class="o-task__flag" style="color:{{ $tone->var() }}"><i @class(['o-badge--pulse' => $overdue])></i>{{ $label }}</div>
    <div class="o-task__t">{{ $title }}</div>
    @if ($module)<div class="o-task__m">{{ $module }}</div>@endif
    @if ($due)<div class="o-task__d">{{ $due }}</div>@endif

    @if ($assignees || $progress !== null)
        <div class="o-task__foot">
            @if ($assignees)
                <div class="o-avs">
                    @foreach ($shown as $person)
                        <x-orbit.avatar :name="$person" size="sm" :tone="$overdue ? 'critical' : null" />
                    @endforeach
                    @if ($extra > 0)<x-orbit.avatar :initials="'+'.$extra" size="sm" />@endif
                </div>
            @endif
            @if ($progress !== null)<span class="o-task__pct">{{ (int) $progress }}%</span>@endif
        </div>
    @endif

    @if ($progress !== null)
        <div class="o-bar" style="margin-top:var(--o-2)"><i style="width:{{ (int) $progress }}%;background:{{ $tone->lit() }}"></i></div>
    @endif
</article>
