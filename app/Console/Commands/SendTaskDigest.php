<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TaskNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The daily task digest — scheduled in routes/console.php.
 *
 *   php artisan tasks:digest --dry-run    who would be mailed, and with what
 */
class SendTaskDigest extends Command
{
    protected $signature = 'tasks:digest {--dry-run : List who would be mailed without sending anything}';

    protected $description = 'Mail each person their overdue, due-today and due-tomorrow tasks';

    public function handle(TaskNotifier $notifier): int
    {
        $dry = (bool) $this->option('dry-run');

        $sent = $notifier->digest(dryRun: $dry, report: function (User $user, array $buckets) {
            $this->line(sprintf('%-28s overdue %d · today %d · tomorrow %d',
                $user->email,
                ...array_map(fn (Collection $c) => $c->count(), array_values($buckets))));
        });

        $this->info(($dry ? 'Would mail ' : 'Mailed ').$sent.' '.str('person')->plural($sent).'.');

        return self::SUCCESS;
    }
}
