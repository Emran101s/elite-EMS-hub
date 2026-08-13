@props(['title', 'subtitle'])

@php
    use App\Models\Task;
    use App\Models\User;
    use Illuminate\Support\Facades\DB;

    $memberCount = User::count();
    $rolesInUse = User::distinct('role')->count('role');

    // Same 'database' session driver NavPanel and every guarded page already
    // rely on for auth — a real sign-in, not an invented "active" flag.
    $activeRecently = DB::table('sessions')
        ->whereNotNull('user_id')
        ->where('last_activity', '>=', now()->subDays(30)->timestamp)
        ->distinct('user_id')
        ->count('user_id');

    $openTasks = Task::whereNotIn('status', ['done', 'cancelled'])->count();
@endphp

<div class="mb-6 space-y-5">
    <x-eo.page-header eyebrow="Team" :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            {{ $actions ?? '' }}
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-eo.metric-pill label="Team members" :value="number_format($memberCount)" hint="Workspace accounts" />
        <x-eo.metric-pill label="Active, 30d" :value="number_format($activeRecently)" hint="Signed in this month" :tone="$activeRecently > 0 ? 'live' : null" />
        <x-eo.metric-pill label="Roles in use" :value="number_format($rolesInUse)" hint="Of 5 available" />
        <x-eo.metric-pill label="Open tasks" :value="number_format($openTasks)" hint="Across the whole book" :tone="$openTasks > 0 ? 'warn' : 'ok'" />
    </div>
</div>
