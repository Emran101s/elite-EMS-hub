<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Export the reference data that exists nowhere but the database.
 *
 * The company profile — legal address, logo path, management fee, budget
 * categories, ticket types and the bank accounts invoices are paid into — was
 * entered by hand. No seeder produces it. `migrate:fresh` destroys it, and
 * nothing in the repository can bring it back.
 *
 * The same is true, in a smaller way, of the taxonomy terms somebody added by
 * hand, and of the transport vocabulary. Everything else in the book is either
 * demo data or reproducible from a seeder.
 *
 * So: this writes those rows to a JSON file that `pilot:import` can read back.
 * The output goes to storage/backups/, which is gitignored — the file carries
 * IBANs and SWIFT codes and must never reach the repository.
 *
 *   php artisan pilot:export
 *   php artisan pilot:export --out=/secure/path/reference.json
 */
class ExportPilotReference extends Command
{
    protected $signature = 'pilot:export {--out= : Where to write (defaults to storage/backups)}';

    protected $description = 'Export company profile, taxonomies and catalogues that no seeder can rebuild';

    /**
     * A vehicle type created while testing the transport module. Excluded from
     * the pilot reference set — the full snapshot taken by db-backup.sh is the
     * faithful copy; this file is the set that should carry forward.
     */
    private const EXCLUDE_VEHICLE_TYPES = ['Test Van'];

    public function handle(): int
    {
        $stamp = now()->format('Ymd-His');
        $path = $this->option('out')
            ?: storage_path("backups/pilot-reference-{$stamp}.json");

        @mkdir(dirname($path), 0775, true);

        // The tenant and workspace rows come first. A fresh migrate creates
        // neither, and every other row here carries a tenant_id foreign key —
        // so without them the import fails on the first insert. Found by
        // round-tripping this export into an empty database, which is the only
        // way that kind of gap ever shows up.
        $tenants = DB::table('tenants')->orderBy('id')->get();
        $workspaces = DB::table('workspaces')->orderBy('id')->get();

        $company = DB::table('company_profiles')->get();
        $taxonomy = DB::table('taxonomy_terms')->where('is_system', false)->orderBy('taxonomy')->orderBy('position')->get();
        $serviceTypes = DB::table('transport_service_types')->orderBy('id')->get();
        $vehicleTypes = DB::table('vehicle_types')->whereNotIn('name', self::EXCLUDE_VEHICLE_TYPES)->orderBy('id')->get();
        $serviceItems = DB::table('service_items')->orderBy('category')->orderBy('name')->get();
        $regTemplates = DB::table('registration_templates')->orderBy('id')->get();

        $payload = [
            'manifest' => [
                'exported_at' => now()->toIso8601String(),
                'source_connection' => config('database.default'),
                'app_env' => app()->environment(),

                // Read this before importing anywhere.
                'warnings' => [
                    'CONTAINS_BANK_DETAILS' => 'company_profiles.bank_accounts holds live IBAN and SWIFT values. Never commit this file. storage/backups/ is gitignored for exactly this reason.',
                    'SERVICE_ITEM_RATES_ARE_PLACEHOLDERS' => "PriceListSeeder's own note: every price is a placeholder and only the units are worth keeping. Codes, categories and units carry forward; every rate must be re-entered and verified before an invoice is raised.",
                    'EXCLUDED_VEHICLE_TYPES' => self::EXCLUDE_VEHICLE_TYPES,
                    'REGISTRATION_TEMPLATES_UNRESOLVED' => 'registration_fields keys on event_id, not on a template, so these three carry no fields of their own. Included so nothing is lost; confirm whether they are wanted before relying on them.',
                ],

                'counts' => [
                    'tenants' => $tenants->count(),
                    'workspaces' => $workspaces->count(),
                    'company_profiles' => $company->count(),
                    'taxonomy_terms_custom' => $taxonomy->count(),
                    'transport_service_types' => $serviceTypes->count(),
                    'vehicle_types' => $vehicleTypes->count(),
                    'service_items' => $serviceItems->count(),
                    'registration_templates' => $regTemplates->count(),
                ],
            ],

            'tenants' => $tenants,
            'workspaces' => $workspaces,
            'company_profiles' => $company,
            'taxonomy_terms_custom' => $taxonomy,
            'transport_service_types' => $serviceTypes,
            'vehicle_types' => $vehicleTypes,
            'service_items' => $serviceItems,
            'registration_templates' => $regTemplates,
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0600);

        $this->info('Pilot reference exported.');
        $this->line("  path   {$path}");
        $this->line('  bytes  '.number_format(filesize($path)));
        $this->line('  sha256 '.hash_file('sha256', $path));
        $this->newLine();

        foreach ($payload['manifest']['counts'] as $table => $n) {
            $this->line(sprintf('  %-26s %d', $table, $n));
        }

        $this->newLine();
        $this->warn('  This file contains bank details. Keep it out of git and off shared drives.');

        return self::SUCCESS;
    }
}
