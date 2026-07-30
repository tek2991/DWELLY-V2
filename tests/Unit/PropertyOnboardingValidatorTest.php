<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\Establishment;
use App\Domain\Property\Models\EstablishmentType;
use App\Domain\Property\Models\PropertyEstablishment;
use App\Domain\Property\Services\PropertyOnboardingValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PropertyOnboardingValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_establishments_is_a_mandatory_step_in_onboarding_validator()
    {
        $property = Property::create([
            'building_name' => 'Test Building',
            'address_line_1' => '123 Main St',
            'status' => 'draft',
        ]);

        $validator = new PropertyOnboardingValidator();
        $result = $validator->validate($property);

        $this->assertArrayHasKey('establishments', $result['steps']);
        $this->assertEquals('Nearby Establishments', $result['steps']['establishments']['name']);
        $this->assertFalse($result['steps']['establishments']['is_valid']);
        $this->assertContains('At least one nearby establishment must be mapped.', $result['steps']['establishments']['missing']);
        $this->assertFalse($result['is_ready']);
    }

    public function test_establishments_step_passes_when_establishment_is_mapped()
    {
        $property = Property::create([
            'building_name' => 'Test Building',
            'address_line_1' => '123 Main St',
            'status' => 'draft',
        ]);

        $type = EstablishmentType::create([
            'name' => 'School',
            'slug' => 'school',
        ]);

        $establishment = Establishment::create([
            'establishment_type_id' => $type->id,
            'name' => 'Greenwood High',
        ]);

        PropertyEstablishment::create([
            'property_id' => $property->id,
            'establishment_id' => $establishment->id,
            'distance_km' => 1.5,
        ]);

        $validator = new PropertyOnboardingValidator();
        $result = $validator->validate($property);

        $this->assertTrue($result['steps']['establishments']['is_valid']);
        $this->assertEmpty($result['steps']['establishments']['missing']);
    }

    public function test_establishments_step_fails_when_distance_is_missing()
    {
        $property = Property::create([
            'building_name' => 'Test Building',
            'address_line_1' => '123 Main St',
            'status' => 'draft',
        ]);

        $type = EstablishmentType::create([
            'name' => 'School',
            'slug' => 'school',
        ]);

        $establishment = Establishment::create([
            'establishment_type_id' => $type->id,
            'name' => 'Greenwood High',
        ]);

        PropertyEstablishment::create([
            'property_id' => $property->id,
            'establishment_id' => $establishment->id,
            'distance_km' => null,
        ]);

        $validator = new PropertyOnboardingValidator();
        $result = $validator->validate($property);

        $this->assertFalse($result['steps']['establishments']['is_valid']);
        $this->assertContains('Distance (KM) must be provided for all mapped establishments.', $result['steps']['establishments']['missing']);
    }

    public function test_establishment_belongs_to_city_and_can_be_filtered()
    {
        $state = \Tek2991\Accounting\Models\State::create([
            'country_id' => 1,
            'name' => 'Karnataka',
            'code' => 'KA',
        ]);

        $district = \App\Domain\Geographic\Models\District::create([
            'state_id' => $state->id,
            'name' => 'District 1',
            'slug' => 'district-1',
        ]);

        $city1 = \App\Domain\Geographic\Models\City::create([
            'district_id' => $district->id,
            'name' => 'Bangalore',
            'slug' => 'bangalore',
        ]);

        $city2 = \App\Domain\Geographic\Models\City::create([
            'district_id' => $district->id,
            'name' => 'Guwahati',
            'slug' => 'guwahati',
        ]);

        $type = EstablishmentType::create([
            'name' => 'IT Park',
            'slug' => 'it-park',
        ]);

        $est1 = Establishment::create([
            'establishment_type_id' => $type->id,
            'city_id' => $city1->id,
            'name' => 'Manyata Tech Park',
        ]);

        $est2 = Establishment::create([
            'establishment_type_id' => $type->id,
            'city_id' => $city2->id,
            'name' => 'Guwahati Tech Park',
        ]);

        $this->assertEquals($city1->id, $est1->city->id);
        $this->assertEquals('Bangalore', $est1->city->name);
        
        $bangaloreEsts = Establishment::where('city_id', $city1->id)->get();
        $this->assertCount(1, $bangaloreEsts);
        $this->assertEquals('Manyata Tech Park', $bangaloreEsts->first()->name);
    }

    public function test_create_property_from_mou_assigns_city_and_locality()
    {
        $state = \Tek2991\Accounting\Models\State::create([
            'country_id' => 1,
            'name' => 'Assam',
            'code' => 'AS',
        ]);

        $district = \App\Domain\Geographic\Models\District::create([
            'state_id' => $state->id,
            'name' => 'Kamrup Metropolitan',
            'slug' => 'kamrup-metropolitan',
        ]);

        $city = \App\Domain\Geographic\Models\City::create([
            'district_id' => $district->id,
            'name' => 'Guwahati',
            'slug' => 'guwahati',
        ]);

        $user = \App\Models\User::factory()->create();

        $opportunity = \App\Domain\Opportunity\Models\Opportunity::create([
            'number' => 'OPP-001',
            'title' => 'Tarun Nagar GF1',
            'owner_name' => 'Jamuna Sharma',
            'owner_phone' => '9876543210',
            'address' => 'H/N 27, Tarun Nagar, Guwahati',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = \App\Domain\Mou\Models\Mou::create([
            'number' => 'MOU-TEST-001',
            'opportunity_id' => $opportunity->id,
            'status' => \App\Domain\Opportunity\Enums\MouStatus::VERIFIED,
            'legal_terms' => [
                'city_id' => $city->id,
                'city_name' => 'Guwahati',
                'address' => 'H/N 27, Tarun Nagar, Guwahati',
            ],
        ]);

        $service = new \App\Domain\Property\Services\PropertyOnboardingService();
        $property = $service->createPropertyFromMou($mou);

        $this->assertEquals('Guwahati', $property->city);
        $this->assertEquals($city->id, $property->city_id);
    }
}
