<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read a pilot-reference export back into a database.
 *
 * The counterpart to `pilot:export`. Built so that standing up the pilot
 * database is a command somebody runs rather than a sequence somebody
 * remembers — the approved rule for Phase 2 is that no migrate:fresh may
 * depend on memory.
 *
 * Idempotent by natural key, not by id: company profile by tenant, taxonomy
 * terms by (taxonomy, key), catalogues by name or code. Running it twice
 * corrects the rows it owns rather than duplicating them.
 *
 *   php artisan pilot:import storage/backups/pilot-reference-20260813-153000.json
 *   php artisan pilot:import <file> --zero-rates    # price list structure only
 *   php artisan pilot:import <file> --dry-run
 */
class ImportPilotReference extends Command
{
    protected $signature = 'pilot:import
        {file : The JSON written by pilot:export}
        {--zero-rates : Import service items with rates set to 0, so no placeholder price can be mistaken for a verified one}
        {--dry-run : Report what would change and write nothing}';

    protected $description = 'Restore company profile, taxonomies and catalogues from a pilot:export file';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_readable($file)) {
            $this->error("Cannot read {$file}");

            return self::FAILURE;
        }

        $payload = json_decode(file_get_contents($file), true);

        if (! is_array($payload) || ! isset($payload['manifest'])) {
            $this->error('That file is not a pilot:export payload.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $this->info($dry ? 'DRY RUN — nothing will be written.' : 'Importing pilot reference.');
        $this->line('  exported at  '.($payload['manifest']['exported_at'] ?? 'unknown'));
        $this->newLine();

        $applied = [];

        DB::transaction(function () use ($payload, $dry, &$applied) {
            // Tenant and workspace first — a fresh migrate creates neither, and
            // everything below carries a tenant_id foreign key.
            foreach (['tenants', 'workspaces'] as $table) {
                foreach ($payload[$table] ?? [] as $row) {
                    $row = (array) $row;
                    $applied[$table] = ($applied[$table] ?? 0) + 1;
                    if (! $dry) {
                        DB::table($table)->updateOrInsert(
                            ['id' => $row['id']],
                            collect($row)->except(['id'])->all(),
                        );
                    }
                }
            }

            // Company profile — the row that cannot be rebuilt from anything.
            foreach ($payload['company_profiles'] ?? [] as $row) {
                $row = (array) $row;
                $applied['company_profiles'] = ($applied['company_profiles'] ?? 0) + 1;
                if (! $dry) {
                    DB::table('company_profiles')->updateOrInsert(
                        ['tenant_id' => $row['tenant_id'] ?? null],
                        collect($row)->except('id')->all(),
                    );
                }
            }

            // Hand-added vocabulary. System terms are recreated by migration.
            foreach ($payload['taxonomy_terms_custom'] ?? [] as $row) {
                $row = (array) $row;
                $applied['taxonomy_terms_custom'] = ($applied['taxonomy_terms_custom'] ?? 0) + 1;
                if (! $dry) {
                    DB::table('taxonomy_terms')->updateOrInsert(
                        ['taxonomy' => $row['taxonomy'], 'key' => $row['key'], 'tenant_id' => $row['tenant_id'] ?? null],
                        collect($row)->except(['id'])->all(),
                    );
                }
            }

            foreach (['transport_service_types', 'vehicle_types', 'registration_templates'] as $table) {
                foreach ($payload[$table] ?? [] as $row) {
                    $row = (array) $row;
                    $applied[$table] = ($applied[$table] ?? 0) + 1;
                    if (! $dry) {
                        DB::table($table)->updateOrInsert(
                            ['name' => $row['name'], 'tenant_id' => $row['tenant_id'] ?? null],
                            collect($row)->except(['id'])->all(),
                        );
                    }
                }
            }

            // Price list. The units are the asset; the rates are placeholders
            // until somebody verifies them, which is what --zero-rates makes
            // physically true rather than only documented.
            foreach ($payload['service_items'] ?? [] as $row) {
                $row = (array) $row;
                if ($this->option('zero-rates')) {
                    $row['unit_price_cents'] = 0;
                }
                $applied['service_items'] = ($applied['service_items'] ?? 0) + 1;
                if (! $dry) {
                    DB::table('service_items')->updateOrInsert(
                        ['code' => $row['code'], 'tenant_id' => $row['tenant_id'] ?? null],
                        collect($row)->except(['id'])->all(),
                    );
                }
            }
        });

        foreach ($applied as $table => $n) {
            $this->line(sprintf('  %-26s %d %s', $table, $n, $dry ? 'would be written' : 'written'));
        }

        $this->newLine();

        if ($this->option('zero-rates')) {
            $this->warn('  Service-item rates set to 0. Enter verified rates before raising an invoice.');
        } else {
            $this->warn('  Service-item rates are PLACEHOLDERS carried from the demo seeder. Verify before invoicing.');
        }

        return self::SUCCESS;
    }
}
