<?php

namespace App\Console\Commands;

use App\Domain\Property\Services\PropertyCsvImportService;
use Illuminate\Console\Command;

class SeedPropertySpecifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dwelly:seed-property-specs
                            {--file=database/seeders/data/property_specifications_template.csv : Path to the specifications CSV file}
                            {--dry-run : Perform validation checks only without committing records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed or update property specifications (rooms, amenities, inventory checklist) from a separate CSV file';

    /**
     * Execute the console command.
     */
    public function handle(PropertyCsvImportService $importService): int
    {
        $filePath = $this->option('file');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("=========================================================");
        $this->info(" Dwelly Property Specifications (Rooms/Amenities/Inventory)");
        $this->info("=========================================================");
        $this->line("Target File: <comment>{$filePath}</comment>");
        $this->line("Execution Mode: " . ($dryRun ? "<fg=yellow;options=bold>DRY RUN (Validation Only)</>" : "<fg=green;options=bold>LIVE UPDATE</>"));
        $this->newLine();

        $result = $importService->importSpecifications($filePath, $dryRun);

        if (!$result['success']) {
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

        $this->info("✓ {$result['message']}");

        if (!empty($result['data'])) {
            $this->table(
                ['Property Code', 'Building Name', 'Rooms Count', 'Amenities Count', 'Inventory Count'],
                $result['data']
            );
        }

        return self::SUCCESS;
    }
}
