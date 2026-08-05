@props([
    'title',
    'body' => null,
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
    'tone' => 'danger', // danger | warn | neutral
    'run',              // Alpine expression run on confirm, e.g. "$wire.delete(1)"
])

{{--
    Shared confirm trigger. Opens the layout's <x-confirm-host> instead of the
    browser's wire:confirm dialog — same gate, our chrome.

    <x-confirm title="Remove Ada?" confirm="Remove" run="$wire.delete({{ $id }})" class="…">
        ✕
    </x-confirm>
--}}
<button type="button"
        {{ $attributes }}
        x-data
        x-on:click.stop.prevent="window.ebhConfirm({
            title: @js($title),
            body: @js($body),
            confirmLabel: @js($confirm),
            cancelLabel: @js($cancel),
            tone: @js($tone),
            run: () => { {!! $run !!} },
        })">
    {{ $slot }}
</button>
