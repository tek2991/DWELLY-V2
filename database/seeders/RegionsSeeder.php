<?php

namespace Database\Seeders;

use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;
use App\Domain\Geographic\Models\Locality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Tek2991\Accounting\Models\State;

class RegionsSeeder extends Seeder
{
    public function run(): void
    {
        $assamState = State::where('name', 'Assam')->first();
        $karnatakaState = State::where('name', 'Karnataka')->first();

        // 1. Guwahati / Assam
        if ($assamState) {
            $kamrup = District::firstOrCreate(
                ['slug' => Str::slug('Kamrup Metropolitan')],
                [
                    'state_id' => $assamState->id,
                    'name' => 'Kamrup Metropolitan',
                    'is_active' => true,
                ]
            );

            $guwahati = City::firstOrCreate(
                ['slug' => Str::slug('Guwahati')],
                [
                    'district_id' => $kamrup->id,
                    'name' => 'Guwahati',
                    'is_active' => true,
                ]
            );

            $localities = ['Azara', 'Beltola', 'Bhangagarh', 'Christian Basti', 'Dispur', 'Ganeshguri', 'Hatigaon', 'Khanapara', 'Six Mile', 'Ulubari', 'Zoo Road', 'Jayanagar', 'Down Town', 'Bhetapara', 'Chandmari', 'Borsajai', 'Panjabari', 'Bamunimaidan'];
            
            foreach ($localities as $locality) {
                Locality::firstOrCreate(
                    [
                        'city_id' => $guwahati->id,
                        'name' => $locality,
                    ],
                    [
                        'slug' => Str::slug("guwahati-{$locality}"),
                        'pincode' => '781001',
                        'is_active' => true,
                    ]
                );
            }
        }

        // 2. Bangalore / Karnataka
        if ($karnatakaState) {
            $blrDistrict = District::firstOrCreate(
                ['slug' => Str::slug('Bangalore Urban')],
                [
                    'state_id' => $karnatakaState->id,
                    'name' => 'Bangalore Urban',
                    'is_active' => true,
                ]
            );

            $bangalore = City::firstOrCreate(
                ['slug' => Str::slug('Bangalore')],
                [
                    'district_id' => $blrDistrict->id,
                    'name' => 'Bangalore',
                    'is_active' => true,
                ]
            );

            $blrLocalities = [
                ['name' => 'Koramangala', 'pincode' => '560034'],
                ['name' => 'Indiranagar', 'pincode' => '560038'],
                ['name' => 'HSR Layout', 'pincode' => '560102'],
                ['name' => 'Whitefield', 'pincode' => '560066'],
                ['name' => 'Bellandur', 'pincode' => '560103'],
                ['name' => 'Electronic City', 'pincode' => '560100'],
                ['name' => 'Jayanagar', 'pincode' => '560041'],
            ];

            foreach ($blrLocalities as $loc) {
                Locality::firstOrCreate(
                    [
                        'city_id' => $bangalore->id,
                        'name' => $loc['name'],
                    ],
                    [
                        'slug' => Str::slug("bangalore-{$loc['name']}"),
                        'pincode' => $loc['pincode'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
