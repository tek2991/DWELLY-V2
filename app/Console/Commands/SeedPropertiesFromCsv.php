<?php

namespace App\Console\Commands;

use App\Domain\Property\Services\PropertyCsvImportService;
use Illuminate\Console\Command;

class SeedPropertiesFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dwelly:seed-properties-csv
                            {--file=database/seeders/data/existing_properties_template.csv : Path to the CSV file}
                            {--specs-file= : Path to the property specifications CSV (rooms, amenities, inventory)}
                            {--dry-run : Perform validation checks only without committing records}
                            {--force : Run without confirmation prompt in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed or import existing properties, MOUs, tenancy agreements, and photos from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle(PropertyCsvImportService $importService): int
    {
        $filePath = $this->option('file');
        $specsFilePath = $this->option('specs-file');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("=================================================");
        $this->info(" Dwelly Existing Properties CSV Importer / Seeder");
        $this->info("=================================================");
        $this->line("Target File: <comment>{$filePath}</comment>");
        if ($specsFilePath) {
            $this->line("Specs File:  <comment>{$specsFilePath}</comment>");
        }
        $this->line("Execution Mode: " . ($dryRun ? "<fg=yellow;options=bold>DRY RUN (Validation Only)</>" : "<fg=green;options=bold>LIVE IMPORT</>"));
        $this->newLine();

        if (!$dryRun && app()->environment('production') && !$this->option('force')) {
            if (!$this->confirm('You are in production! Do you wish to proceed with property seeding?')) {
                $this->warn('Aborted by user.');
                return self::SUCCESS;
            }
        }

        $this->info('Parsing CSV file and validating structure...');
        $result = $importService->import($filePath, $dryRun, $specsFilePath);

        if (!empty($result['warnings'])) {
            $this->newLine();
            $this->warn('Warnings encountered:');
            foreach ($result['warnings'] as $warning) {
                $this->line("  - <fg=yellow>{$warning}</>");
            }
        }

        if (!$result['success']) {
            $this->newLine();
            $this->error('Failed: ' . $result['message']);
            if (!empty($result['errors'])) {
                $this->newLine();
                $this->error('Errors:');
                foreach ($result['errors'] as $error) {
                    $this->line("  - <fg=red>{$error}</>");
                }
            }
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info("✓ {$result['message']}");
            if (!empty($result['rows_summary'])) {
                $this->table(
                    ['Row #', 'Building Name', 'City', 'Owner', 'Status'],
                    $result['rows_summary']
                );
            }
            $this->info('All validation checks passed. You can run without --dry-run to seed the database.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("✓ {$result['message']}");

        if (!empty($result['data'])) {
            $this->table(
                ['Row #', 'Property Code', 'Building Name', 'Status'],
                $result['data']
            );
        }

        $this->newLine();
        $this->info('Properties, MOUs, Tenancy Agreements, Owners, Tenants & Photos have been seeded successfully!');
        return self::SUCCESS;
    }
}
