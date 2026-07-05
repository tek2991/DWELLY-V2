<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegionsSeeder extends Seeder
{
    public function run(): void
    {
        $assamState = \Tek2991\Accounting\Models\State::where('name', 'Assam')->first();

        if (! $assamState) {
            return;
        }

        // District
        $kamrup = \App\Domain\Geographic\Models\District::create([
            'state_id' => $assamState->id,
            'name' => 'Kamrup Metropolitan',
            'slug' => Str::slug('Kamrup Metropolitan'),
            'is_active' => true,
        ]);

        // City
        $guwahati = \App\Domain\Geographic\Models\City::create([
            'district_id' => $kamrup->id,
            'name' => 'Guwahati',
            'slug' => Str::slug('Guwahati'),
            'is_active' => true,
        ]);

        // Localities
        $localities = ['Azara', 'Beltola', 'Bhangagarh', 'Christian Basti', 'Dispur', 'Ganeshguri', 'Hatigaon', 'Khanapara', 'Six Mile', 'Ulubari', 'Zoo Road'];
        
        foreach ($localities as $locality) {
            \App\Domain\Geographic\Models\Locality::create([
                'city_id' => $guwahati->id,
                'name' => $locality,
                'slug' => Str::slug($locality),
                'pincode' => '781001',
                'is_active' => true,
            ]);
        }
    }
}
