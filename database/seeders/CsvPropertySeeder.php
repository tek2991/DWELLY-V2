<?php

namespace Database\Seeders;

use App\Domain\Property\Services\PropertyCsvImportService;
use Illuminate\Database\Seeder;

class CsvPropertySeeder extends Seeder
{
    /**
     * Run foundational system seeds followed by CSV property imports.
     */
    public function run(): void
    {
        $this->call(SystemBaseSeeder::class);

        $csvCandidates = [
            base_path('database/seeders/data/existing_properties_data.csv'),
            base_path('database/seeders/data/existing_properties_template.csv'),
        ];

        $csvFile = null;
        foreach ($csvCandidates as $candidate) {
            if (file_exists($candidate) && is_readable($candidate)) {
                $csvFile = $candidate;
                break;
            }
        }

        if (!$csvFile) {
            $this->command->error('No properties CSV data file found to import.');
            return;
        }

        $this->command->info("Running CsvPropertySeeder using: {$csvFile}");

        $importer = app(PropertyCsvImportService::class);
        $result = $importer->import($csvFile, false);

        if (!$result['success']) {
            $this->command->error("CsvPropertySeeder failed: " . ($result['message'] ?? 'Unknown error'));
            foreach (($result['errors'] ?? []) as $error) {
                $this->command->error(" - {$error}");
            }
            return;
        }

        $this->command->info("✓ " . ($result['message'] ?? 'Import completed successfully.'));
        $this->command->info("✓ Imported: " . ($result['imported_rows'] ?? 0) . " properties.");
    }
}
