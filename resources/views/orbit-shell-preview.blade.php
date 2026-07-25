@php
    $event = \App\Models\Event::query()->findOrFail(7);
    $module = request('tab', 'overview');
@endphp
<x-layouts.orbit :event="$event" :module="$module" :kpis="\App\Support\OrbitShell::kpis($event)">
    <x-orbit.card gravity="hero">
        <x-orbit.greeting heading="Good morning, {{ \Illuminate\Support\Str::of(auth()->user()?->name ?? 'there')->explode(' ')->first() }}">
            <x-slot:summary>Transport is the critical path — <b>18 movements</b> are still unassigned with 106 days to go.</x-slot:summary>
        </x-orbit.greeting>
        <div style="margin-top:var(--o-5)">
            <x-orbit.quick-add :items="[
                ['label' => 'Task', 'icon' => 'task'],
                ['label' => 'Session', 'icon' => 'cal'],
                ['label' => 'Supplier', 'icon' => 'truck'],
                ['label' => 'Expense', 'icon' => 'money'],
                ['label' => 'Note', 'icon' => 'note'],
            ]" />
        </div>
    </x-orbit.card>

    <x-orbit.card title="Delivery journey">
        <x-orbit.gantt :today="62" :scale="['Jul','Aug','Sep','Oct','Nov']" :bands="[
            ['label' => 'Planning', 'start' => 0, 'end' => 38, 'tone' => 'plasma'],
            ['label' => 'Production', 'start' => 30, 'end' => 78, 'tone' => 'ion'],
            ['label' => 'Show days', 'start' => 78, 'end' => 92, 'tone' => 'gold'],
            ['label' => 'Close-out', 'start' => 92, 'end' => 100, 'tone' => 'vital'],
        ]" />
    </x-orbit.card>

    <x-slot:rails>
        <x-orbit.card title="Event Pulse">
            <x-orbit.pulse :metrics="[
                ['label' => 'Overdue tasks', 'value' => '10', 'tone' => 'critical'],
                ['label' => 'Open tasks', 'value' => '24', 'tone' => 'ion'],
                ['label' => 'Budget used', 'value' => '0%', 'tone' => 'vital'],
                ['label' => 'Approvals pending', 'value' => '6', 'tone' => 'flare'],
            ]" />
        </x-orbit.card>
        <x-orbit.ai-panel
            :insights="[
                ['title' => '10 tasks are overdue', 'sub' => 'Eight sit with one owner — rebalance before Friday.', 'tone' => 'critical', 'icon' => 'warn'],
                ['title' => 'No suppliers engaged yet', 'sub' => '106 days out; venue and catering usually lock by now.', 'tone' => 'flare', 'icon' => 'truck'],
                ['title' => 'JD 350,000 collected', 'sub' => 'Client contract is fully paid.', 'tone' => 'vital', 'icon' => 'money'],
            ]"
            :chips="['Show critical tasks', 'Budget forecast', 'Risk analysis']" />
    </x-slot:rails>
</x-layouts.orbit>
