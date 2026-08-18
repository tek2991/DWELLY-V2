<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Domain\Property\Models\EstablishmentType;
use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;

class EstablishmentSeeder extends Seeder
{
    public function run(): void
    {
        $types = EstablishmentType::all()->keyBy('name');

        if ($types->isEmpty()) {
            $this->command->warn('Establishment types not found. Run ReferenceDataSeeder first.');
            return;
        }

        $defaultDistrict = District::first() ?? District::create([
            'state_id' => \Tek2991\Accounting\Models\State::first()?->id ?? 1,
            'name' => 'General District',
            'slug' => 'general-district',
            'is_active' => true,
        ]);

        $karnataka = \Tek2991\Accounting\Models\State::where('name', 'Karnataka')->first();
        $bangaloreDistrict = District::firstOrCreate(
            ['name' => 'Bangalore Urban'],
            [
                'state_id' => $karnataka?->id ?? 1,
                'slug' => 'bangalore-urban',
                'is_active' => true,
            ]
        );

        $bangalore = City::firstOrCreate(
            ['name' => 'Bangalore'],
            [
                'district_id' => $bangaloreDistrict->id,
                'slug' => 'bangalore',
                'is_active' => true,
            ]
        );

        $guwahati = City::where('name', 'Guwahati')->first() ?? City::firstOrCreate(
            ['name' => 'Guwahati'],
            [
                'district_id' => $defaultDistrict->id,
                'slug' => 'guwahati',
                'is_active' => true,
            ]
        );

        // Map establishment types: 3 for Guwahati only, 3 for Bangalore only, 2 for both
        $guwahatiOnly = ['Railway Station', 'School', 'Park'];
        $bangaloreOnly = ['IT Park', 'Metro Station', 'Shopping Mall'];
        $bothCities = ['Airport', 'Hospital'];

        $guwahatiTypeIds = collect([...$guwahatiOnly, ...$bothCities])
            ->map(fn ($name) => $types->get($name)?->id)
            ->filter()
            ->values()
            ->toArray();

        $bangaloreTypeIds = collect([...$bangaloreOnly, ...$bothCities])
            ->map(fn ($name) => $types->get($name)?->id)
            ->filter()
            ->values()
            ->toArray();

        $guwahati->establishmentTypes()->sync($guwahatiTypeIds);
        $bangalore->establishmentTypes()->sync($bangaloreTypeIds);

        $establishments = [
            // Guwahati (Guwahati-only & Both)
            [
                'name' => 'Lokpriya Gopinath Bordoloi International Airport',
                'type' => 'Airport',
                'address' => 'Borjhar',
                'city' => 'Guwahati',
                'latitude' => 26.1061,
                'longitude' => 91.5859,
            ],
            [
                'name' => 'Gauhati Medical College & Hospital',
                'type' => 'Hospital',
                'address' => 'Bhangagarh',
                'city' => 'Guwahati',
                'latitude' => 26.1554,
                'longitude' => 91.7686,
            ],
            [
                'name' => 'Guwahati Railway Station',
                'type' => 'Railway Station',
                'address' => 'Paltan Bazaar',
                'city' => 'Guwahati',
                'latitude' => 26.1824,
                'longitude' => 91.7506,
            ],
            [
                'name' => 'Cotton Collegiate School',
                'type' => 'School',
                'address' => 'Panbazar',
                'city' => 'Guwahati',
                'latitude' => 26.1884,
                'longitude' => 91.7455,
            ],
            [
                'name' => 'Nehru Park',
                'type' => 'Park',
                'address' => 'Panbazar',
                'city' => 'Guwahati',
                'latitude' => 26.1865,
                'longitude' => 91.7470,
            ],

            // Bangalore (Bangalore-only & Both)
            [
                'name' => 'Kempegowda International Airport',
                'type' => 'Airport',
                'address' => 'Devanahalli',
                'city' => 'Bangalore',
                'latitude' => 13.1989,
                'longitude' => 77.7068,
            ],
            [
                'name' => 'Manipal Hospital',
                'type' => 'Hospital',
                'address' => 'HAL Airport Road',
                'city' => 'Bangalore',
                'latitude' => 12.9587,
                'longitude' => 77.6493,
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
        ];

        $now = now();
        $data = [];

        foreach ($establishments as $est) {
            $typeId = $types->get($est['type'])?->id;
            
            if (!$typeId) {
                continue;
            }

            $city = $est['city'] === 'Bangalore' ? $bangalore : $guwahati;

            $data[] = [
                'id' => (string) Str::ulid(),
                'name' => $est['name'],
                'establishment_type_id' => $typeId,
                'address' => $est['address'],
                'city_id' => $city->id,
                'latitude' => $est['latitude'],
                'longitude' => $est['longitude'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('establishments')->insert($data);
    }
}
