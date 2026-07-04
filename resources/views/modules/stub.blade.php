<x-layouts.app :title="$module['label']" :subtitle="$module['blurb']">
    <div class="card flex flex-col items-center px-8 py-16 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-navy-50 text-navy-600">
            <x-icon :name="$module['icon']" class="h-7 w-7" />
        </span>
        <h2 class="mt-5 text-lg font-bold text-navy-900">{{ $module['label'] }} is on the way</h2>
        <p class="mt-2 max-w-md text-sm text-muted">{{ $module['blurb'] }}</p>
        <span class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 text-xs font-semibold text-gold-700 ring-1 ring-gold-200">
            Planned · {{ $module['phase'] }}
        </span>
    </div>
</x-layouts.app>
