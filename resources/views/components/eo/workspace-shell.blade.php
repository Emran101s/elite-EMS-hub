@props([
    'crumbs' => null,
    'title' => null,
])

{{-- Soft Command WorkspaceShell — soft gray canvas + top command bar. --}}
<section {{ $attributes->class(['eo-workspace-shell']) }}>
    <x-eo.top-command-bar :crumbs="$crumbs" :title="$title" />
    <div class="eo-workspace-body">
        {{ $slot }}
    </div>
</section>
