@props([
    'title' => 'AI Event Director',
    // insights: [['title'=>'…','sub'=>'…','tone'=>'flare','icon'=>'warn'], …]
    'insights' => [],
    'chips' => [],       // suggested prompts
    'prompt' => true,
])
<div {{ $attributes->merge(['class' => 'o-panel']) }}>
    <div class="o-ai__head">
        <x-orbit.icon name="spark" :size="16" />
        <h3 class="o-card__title">{{ $title }}</h3>
    </div>
    <div class="o-card__pad">
        @foreach ($insights as $i)
            @php $t = \App\Support\Tone::tryFrom($i['tone'] ?? 'ion') ?? \App\Support\Tone::Ion; @endphp
            <div class="o-ai__item">
                <span class="o-ai__ico" style="background:{{ $t->tint() }};color:{{ $t->var() }}">
                    <x-orbit.icon :name="$i['icon'] ?? 'spark'" :size="13" />
                </span>
                <div>
                    <div class="o-ai__t">{{ $i['title'] ?? '' }}</div>
                    @if (! empty($i['sub']))<div class="o-ai__s">{{ $i['sub'] }}</div>@endif
                </div>
            </div>
        @endforeach

        @if ($chips)
            <div class="o-chips" style="margin-top:var(--o-4)">
                @foreach ($chips as $c)<x-orbit.chip>{{ $c }}</x-orbit.chip>@endforeach
            </div>
        @endif

        @if ($prompt)
            <div class="o-ai__prompt" style="margin-top:var(--o-4)">
                <input type="text" placeholder="Ask about this event…" aria-label="Ask the AI Event Director">
                <button type="button" class="o-ai__send" aria-label="Send"><x-orbit.icon name="send" :size="15" /></button>
            </div>
        @endif
    </div>
</div>
