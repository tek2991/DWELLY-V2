<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Domain\Property\Models\EstablishmentType;

class EstablishmentSeeder extends Seeder
{
    public function run(): void
    {
        $types = EstablishmentType::all()->keyBy('name');

        if ($types->isEmpty()) {
            $this->command->warn('Establishment types not found. Run ReferenceDataSeeder first.');
            return;
        }

        $establishments = [
            [
                'name' => 'Apollo Hospital',
                'type' => 'Hospital',
                'address' => 'Greams Road',
                'city' => 'Chennai',
                'latitude' => 13.0617,
                'longitude' => 80.2543,
            ],
            [
                'name' => 'Manyata Tech Park',
                'type' => 'IT Park',
                'address' => 'Hebbal',
                'city' => 'Bangalore',
                'latitude' => 13.0450,
                'longitude' => 77.6206,
            ],
            [
                'name' => 'Delhi Public School',
                'type' => 'School',
                'address' => 'RK Puram',
                'city' => 'New Delhi',
                'latitude' => 28.5630,
                'longitude' => 77.1812,
            ],
            [
                'name' => 'Indiranagar Metro Station',
                'type' => 'Metro Station',
                'address' => 'Indiranagar',
                'city' => 'Bangalore',
                'latitude' => 12.9784,
                'longitude' => 77.6385,
            ],
            [
                'name' => 'Phoenix Marketcity',
                'type' => 'Shopping Mall',
                'address' => 'Whitefield',
                'city' => 'Bangalore',
                'latitude' => 12.9960,
                'longitude' => 77.6953,
            ],
            [
                'name' => 'Kempegowda International Airport',
                'type' => 'Airport',
                'address' => 'Devanahalli',
                'city' => 'Bangalore',
                'latitude' => 13.1989,
                'longitude' => 77.7068,
            ],
        ];

        $now = now();
        $data = [];

        foreach ($establishments as $est) {
            $typeId = $types->get($est['type'])?->id;
            
            if (!$typeId) {
                continue;
            }

            $data[] = [
                'id' => (string) Str::ulid(),
                'name' => $est['name'],
                'establishment_type_id' => $typeId,
                'address' => $est['address'],
                'city' => $est['city'],
                'latitude' => $est['latitude'],
                'longitude' => $est['longitude'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('establishments')->insert($data);
    }
}
