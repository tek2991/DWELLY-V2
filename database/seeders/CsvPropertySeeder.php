<?php

namespace Database\Seeders;

use App\Domain\Property\Services\PropertyCsvImportService;
use Illuminate\Database\Seeder;

class CsvPropertySeeder extends Seeder
{
    /**
     * Run the database seeds from CSV template.
     */
    public function run(): void
    {
        $csvFile = base_path('database/seeders/data/existing_properties_template.csv');

        $this->command->info("Running CsvPropertySeeder using: {$csvFile}");

        $importer = app(PropertyCsvImportService::class);
        $result = $importer->import($csvFile, false);

        if (!$result['success']) {
            $this->command->error("CsvPropertySeeder failed: " . $result['message']);
            foreach ($result['errors'] as $error) {
                $this->command->error(" - {$error}");
            }
            return;
        }

        $this->command->info("✓ " . $result['message']);
    }
}
