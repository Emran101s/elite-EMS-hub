@props(['event', 'header'])

{{--
    Command Stack — every card here is one of EventCommandHeader::attention()'s
    six real signals (tasks/risks/approvals/speakers/budget/contract), the
    same numbers the header pills and Priority Area already read. A signal
    that isn't firing (count === 0) simply isn't in $header['attention'] at
    all, so there is nothing to fake here — the stack shows exactly as many
    cards as there are real things needing a person.
--}}

@php
    $meta = [
        'tasks' => ['label' => 'Tasks Overdue', 'icon' => 'clipboard', 'tab' => 'tasks', 'cta' => 'Review Tasks'],
        'risks' => ['label' => 'Risks Open', 'icon' => 'bell', 'tab' => 'risks', 'cta' => 'Review Risks'],
        'approvals' => ['label' => 'Approvals Waiting', 'icon' => 'identification', 'tab' => 'approvals', 'cta' => 'Review Approvals'],
        'speakers' => ['label' => 'Speakers Pending', 'icon' => 'sparkles', 'tab' => 'speakers', 'cta' => 'Review Speakers'],
        'budget' => ['label' => 'Budget Attention', 'icon' => 'currency', 'tab' => 'budget', 'cta' => 'Review Budget'],
        'contract' => ['label' => 'Contract Pending', 'icon' => 'archive', 'tab' => 'contract', 'cta' => 'Review Contract'],
    ];

    $toneColor = fn (string $tone) => match ($tone) {
        'alarm' => 'var(--color-eo-risk)',
        'wait' => 'var(--color-eo-warn)',
        default => 'var(--color-eo-teal)',
    };

    $cards = collect($header['attention'] ?? [])
        ->filter(fn ($signal, $key) => array_key_exists($key, $meta))
        ->map(function ($signal, $key) use ($meta, $event, $toneColor) {
            $m = $meta[$key];

            // Cheap, already-loaded-relation avatars — one per signal type,
            // read off the exact records the count itself came from.
            $avatars = match ($key) {
                'approvals' => $event->approvals->where('status', 'pending')
                    ->sortBy('created_at')->take(3)->map->requester->filter()->values(),
                'tasks' => $event->tasks->filter(fn ($t) => $t->isOpen() && $t->due_on?->isPast())
                    ->sortBy('due_on')->take(3)->map->assignee->filter()->values(),
                default => collect(),
            };

            return [
                'key' => $key,
                'label' => $m['label'],
                'icon' => $m['icon'],
                'cta' => $m['cta'],
                'count' => $signal['count'],
                'why' => $signal['why'],
                'color' => $toneColor($signal['tone']),
                'avatars' => $avatars,
                'href' => route('events.hub', [$event, 'tab' => $m['tab']]),
            ];
        })
        ->values();
@endphp

<div class="hubx-stack">
    <div class="hubx-stack-head">
        <span class="hubx-stack-title">
            <x-icon name="bell" class="h-3.5 w-3.5 text-eo-teal-lit" />
            Command Stack
        </span>
        @if ($cards->isNotEmpty())
            <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-white/70">{{ $cards->count() }}</span>
        @endif
    </div>

    @if ($cards->isEmpty())
        <div class="hubx-stack-empty">Clear — nothing needs a person right now.</div>
    @else
        @foreach ($cards as $card)
            <a href="{{ $card['href'] }}" wire:navigate class="hubx-stack-card" style="--sc-color: {{ $card['color'] }}">
                <div class="hubx-stack-card-head">
                    <span class="hubx-stack-icon">
                        <x-icon :name="$card['icon']" class="h-3.5 w-3.5" />
                    </span>
                    <span class="hubx-stack-card-title">{{ $card['label'] }}</span>
                </div>
                <p class="hubx-stack-card-sub">{{ ucfirst($card['why']) }}</p>

                @if ($card['avatars']->isNotEmpty())
                    <div class="hubx-stack-avatars">
                        @foreach ($card['avatars'] as $person)
                            @if ($person->avatar_path)
                                <img src="{{ asset($person->avatar_path) }}" alt="{{ $person->name }}" class="hubx-stack-avatar">
                            @else
                                <span class="hubx-stack-avatar grid place-items-center bg-eo-navy text-[8px] font-bold text-eo-gold-soft">{{ $person->initials() }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif

                <span class="hubx-stack-cta">{{ $card['cta'] }} →</span>
            </a>
        @endforeach
    @endif

    <a href="{{ route('events.hub', [$event, 'tab' => 'risks']) }}" wire:navigate class="hubx-stack-viewall">
        View all signals
    </a>
</div>
