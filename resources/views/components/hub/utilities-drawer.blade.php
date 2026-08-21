@props(['event', 'workload'])

@php
    $activity = $event->auditLogs()->with('user')->latest()->limit(20)->get();
@endphp

<div x-data="{ tab: 'workload' }">
    <div x-show="utilitiesOpen" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="utilitiesOpen = false" class="ehx-utilities-backdrop"></div>

    <div x-show="utilitiesOpen" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         x-on:keydown.escape.window="utilitiesOpen = false"
         class="ehx-utilities-drawer">
        <div class="ehx-utilities-head">
            <p class="ehx-utilities-title">Event Utilities</p>
            <button type="button" @click="utilitiesOpen = false" class="ehx-utilities-close">Close ✕</button>
        </div>

        <div class="ehx-utilities-tabs">
            <button type="button" @click="tab = 'workload'" class="ehx-utilities-tab" :class="tab === 'workload' ? 'is-active' : ''">Team Workload</button>
            <button type="button" @click="tab = 'activity'" class="ehx-utilities-tab" :class="tab === 'activity' ? 'is-active' : ''">Recent Activity</button>
        </div>

        <div x-show="tab === 'workload'" class="ehx-utilities-body">
            @forelse ($workload as $row)
                <div class="ehx-utilities-row">
                    <x-user-avatar :user="$row['user']" size="h-8 w-8" text="text-3xs" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="ehx-utilities-row-title">{{ $row['user']->name }}</p>
                            <span class="ehx-utilities-row-when">{{ $row['pct'] }}%</span>
                        </div>
                        <p class="ehx-utilities-row-sub">{{ str($row['user']->pivot->role)->replace('_', ' ')->title() }}</p>
                        <div class="ehx-utilities-bar">
                            <div class="ehx-utilities-bar-fill" style="width: {{ $row['pct'] }}%; background: {{ $row['pct'] >= 80 ? 'var(--color-danger)' : ($row['pct'] >= 60 ? 'var(--color-warning)' : 'var(--color-success)') }}"></div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="ehx-utilities-empty">No team assigned yet.</p>
            @endforelse
        </div>

        <div x-show="tab === 'activity'" x-cloak class="ehx-utilities-body">
            @forelse ($activity as $log)
                <div class="ehx-utilities-row">
                    @if ($log->user)
                        <x-user-avatar :user="$log->user" size="h-7 w-7" text="text-3xs" />
                    @else
                        <span class="ehx-utilities-row-system"><x-icon name="cog" class="h-3.5 w-3.5" /></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="ehx-utilities-row-title">
                            <span class="font-bold">{{ $log->user?->name ?? 'System' }}</span>
                            {{ ['created' => 'created', 'updated' => 'changed', 'deleted' => 'deleted'][$log->action] ?? $log->action }}
                            <span class="font-bold">{{ $log->label }}</span>
                        </p>
                        <p class="ehx-utilities-row-sub">{{ $log->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="ehx-utilities-empty">Nothing logged yet.</p>
            @endforelse
        </div>
    </div>
</div>
