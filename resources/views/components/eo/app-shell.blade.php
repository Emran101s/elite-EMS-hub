@props([
    'title' => null,
    'crumbs' => null,
])

{{-- Soft Command AppShell — MiniRail + ContextSidebar + WorkspaceShell. --}}
<div {{ $attributes->class(['eo-app-shell']) }}>
    <x-eo.mini-rail />
    <x-eo.context-sidebar />
    <x-eo.workspace-shell :crumbs="$crumbs" :title="$title">
        {{ $slot }}
    </x-eo.workspace-shell>
</div>
