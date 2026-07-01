<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Domain\Geographic\Models\Region;

class RegionsSeeder extends Seeder
{
    public function run(): void
    {
        // Level 1: State
        $assam = Region::create([
            'name' => 'Assam',
            'slug' => Str::slug('Assam'),
            'level' => 1,
            'is_active' => true,
        ]);

        // Level 2: District/City
        $guwahati = Region::create([
            'name' => 'Guwahati',
            'slug' => Str::slug('Guwahati'),
            'parent_id' => $assam->id,
            'level' => 2,
            'is_active' => true,
        ]);

        // Level 3: Localities
        $localities = ['Azara', 'Beltola', 'Bhangagarh', 'Christian Basti', 'Dispur', 'Ganeshguri', 'Hatigaon', 'Khanapara', 'Six Mile', 'Ulubari', 'Zoo Road'];
        
        foreach ($localities as $locality) {
            Region::create([
                'name' => $locality,
                'slug' => Str::slug($locality),
                'parent_id' => $guwahati->id,
                'level' => 3,
                'is_active' => true,
            ]);
        }
    }
}
