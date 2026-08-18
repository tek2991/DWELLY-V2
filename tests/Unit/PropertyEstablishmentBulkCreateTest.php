<?php

namespace Tests\Unit;

use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;
use App\Domain\Property\Models\Establishment;
use App\Domain\Property\Models\EstablishmentType;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyEstablishment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Models\State;
use Tests\TestCase;

class PropertyEstablishmentBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_create_links_existing_and_creates_new_establishments()
    {
        $state = State::create([
            'country_id' => 1,
            'name' => 'Assam',
            'code' => 'AS',
        ]);

        $district = District::create([
            'state_id' => $state->id,
            'name' => 'Kamrup Metropolitan',
            'slug' => 'kamrup-metropolitan',
        ]);

        $city = City::create([
            'district_id' => $district->id,
            'name' => 'Guwahati',
            'slug' => 'guwahati',
        ]);

        $locality = \App\Domain\Geographic\Models\Locality::create([
            'city_id' => $city->id,
            'name' => 'Zoo Road',
            'slug' => 'zoo-road',
        ]);

        $property = Property::create([
            'building_name' => 'Green Heights',
            'address_line_1' => 'Zoo Road',
            'locality_id' => $locality->id,
            'city' => 'Guwahati',
            'status' => 'draft',
        ]);

        $hospitalType = EstablishmentType::create([
            'name' => 'Hospital',
            'slug' => 'hospital',
            'is_active' => true,
        ]);

        $schoolType = EstablishmentType::create([
            'name' => 'School',
            'slug' => 'school',
            'is_active' => true,
        ]);

        // Pre-existing establishment
        $existingHospital = Establishment::create([
            'name' => 'Apollo Hospital',
            'establishment_type_id' => $hospitalType->id,
            'city_id' => $city->id,
        ]);

        // Simulate bulkCreate action processing items with text input establishment_name:
        // Item 1: Uses existing hospital name 'Apollo Hospital'
        // Item 2: Creates new school by name 'St. Francis Academy'
        $items = [
            [
                'establishment_type_id' => $hospitalType->id,
                'establishment_name' => 'Apollo Hospital',
                'distance_km' => 2.5,
                'travel_time_minutes' => 10,
            ],
            [
                'establishment_type_id' => $schoolType->id,
                'establishment_name' => 'St. Francis Academy',
                'distance_km' => 1.2,
                'travel_time_minutes' => 5,
            ],
            [
                'establishment_type_id' => $hospitalType->id,
                'establishment_name' => '',
                'distance_km' => null,
                'travel_time_minutes' => null,
            ],
        ];

        foreach ($items as $item) {
            $typeId = $item['establishment_type_id'] ?? null;
            $name = isset($item['establishment_name']) ? trim($item['establishment_name']) : null;
            $dist = $item['distance_km'] ?? null;
            $time = $item['travel_time_minutes'] ?? null;

            if (empty($name) || $dist === null || $dist === '') {
                continue;
            }

            $establishment = Establishment::firstOrCreate([
                'name' => $name,
                'establishment_type_id' => $typeId,
                'city_id' => $property->city_id,
            ]);

            $existing = $property->establishments()
                ->where('establishment_id', $establishment->id)
                ->first();

            if (!$existing) {
                $property->establishments()->create([
                    'establishment_id' => $establishment->id,
                    'distance_km' => $dist,
                    'travel_time_minutes' => $time,
                ]);
            }
        }

        // Verify existing hospital was linked
        $this->assertDatabaseHas('property_establishments', [
            'property_id' => $property->id,
            'establishment_id' => $existingHospital->id,
            'distance_km' => 2.5,
            'travel_time_minutes' => 10,
        ]);

        // Verify new school was created in establishments table and linked to property
        $newSchool = Establishment::where('name', 'St. Francis Academy')->first();
        $this->assertNotNull($newSchool);
        $this->assertEquals($schoolType->id, $newSchool->establishment_type_id);
        $this->assertEquals($city->id, $newSchool->city_id);

        $this->assertDatabaseHas('property_establishments', [
            'property_id' => $property->id,
            'establishment_id' => $newSchool->id,
            'distance_km' => 1.2,
            'travel_time_minutes' => 5,
        ]);

        // Verify total property establishments count is 2
        $this->assertEquals(2, $property->establishments()->count());
    }

    public function test_establishment_type_and_city_many_to_many_relationship()
    {
        $state = State::create(['name' => 'Assam', 'code' => 'AS', 'country_id' => 1]);
        $district = District::create(['state_id' => $state->id, 'name' => 'Kamrup', 'slug' => 'kamrup']);
        $cityGuwahati = City::create(['district_id' => $district->id, 'name' => 'Guwahati', 'slug' => 'guwahati']);
        $citySilchar = City::create(['district_id' => $district->id, 'name' => 'Silchar', 'slug' => 'silchar']);

        $airport = EstablishmentType::create([
            'name' => 'Airport',
            'slug' => 'airport',
            'is_active' => true,
        ]);

        $hospital = EstablishmentType::create([
            'name' => 'Hospital',
            'slug' => 'hospital',
            'is_active' => true,
        ]);

        // Attach cities to establishment types
        $airport->cities()->attach([$cityGuwahati->id, $citySilchar->id]);
        $hospital->cities()->attach([$cityGuwahati->id]);

        $this->assertCount(2, $airport->fresh()->cities);
        $this->assertCount(1, $hospital->fresh()->cities);
        $this->assertCount(2, $cityGuwahati->fresh()->establishmentTypes);
        $this->assertCount(1, $citySilchar->fresh()->establishmentTypes);
    }
}
