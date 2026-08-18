<?php

namespace Tests\Feature;

use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;
use App\Domain\Property\Models\EstablishmentType;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\RelationManagers\EstablishmentsRelationManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Models\State;
use Tests\TestCase;

class EstablishmentBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_create_modal_prepopulates_establishment_types_mapped_to_property_city()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $state = State::create(['name' => 'Assam', 'code' => 'AS', 'country_id' => 1]);
        $district = District::create(['state_id' => $state->id, 'name' => 'Kamrup', 'slug' => 'kamrup']);
        $cityGuwahati = City::create(['district_id' => $district->id, 'name' => 'Guwahati', 'slug' => 'guwahati']);
        $cityShillong = City::create(['district_id' => $district->id, 'name' => 'Shillong', 'slug' => 'shillong']);

        $property = Property::create([
            'building_name' => 'Test Heights',
            'address_line_1' => 'Test Road',
            'city' => 'Guwahati',
            'status' => 'draft',
        ]);

        $hospitalType = EstablishmentType::create([
            'name' => 'Hospital',
            'slug' => 'hospital',
            'is_active' => true,
        ]);
        $hospitalType->cities()->attach($cityGuwahati->id);

        $schoolType = EstablishmentType::create([
            'name' => 'School',
            'slug' => 'school',
            'is_active' => true,
        ]);
        $schoolType->cities()->attach($cityGuwahati->id);

        $metroType = EstablishmentType::create([
            'name' => 'Metro Station',
            'slug' => 'metro-station',
            'is_active' => true,
        ]);
        $metroType->cities()->attach($cityShillong->id); // Mapped to Shillong, not Guwahati

        Livewire::test(EstablishmentsRelationManager::class, [
            'ownerRecord' => $property,
            'pageClass' => \App\Filament\Resources\Properties\Pages\EditProperty::class,
        ])
            ->mountTableAction('bulkCreate')
            ->assertTableActionMounted('bulkCreate')
            ->assertTableActionDataSet([
                'selected_cities' => [$cityGuwahati->id],
            ]);
    }

    public function test_bulk_create_action_submits_establishments_successfully()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $state = State::create(['name' => 'Assam', 'code' => 'AS', 'country_id' => 1]);
        $district = District::create(['state_id' => $state->id, 'name' => 'Kamrup', 'slug' => 'kamrup']);
        $city = City::create(['district_id' => $district->id, 'name' => 'Guwahati', 'slug' => 'guwahati']);

        $property = Property::create([
            'building_name' => 'Property in Guwahati',
            'address_line_1' => 'Road A',
            'city' => 'Guwahati',
            'status' => 'draft',
        ]);

        $hospitalType = EstablishmentType::create([
            'name' => 'Hospital',
            'slug' => 'hospital',
            'is_active' => true,
        ]);
        $hospitalType->cities()->attach($city->id);

        $hospital = \App\Domain\Property\Models\Establishment::create([
            'name' => 'City Hospital',
            'establishment_type_id' => $hospitalType->id,
            'city_id' => $city->id,
        ]);

        Livewire::test(EstablishmentsRelationManager::class, [
            'ownerRecord' => $property,
            'pageClass' => \App\Filament\Resources\Properties\Pages\EditProperty::class,
        ])
            ->mountTableAction('bulkCreate')
            ->setTableActionData([
                'selected_cities' => [$city->id],
                'establishments' => [
                    [
                        'is_default' => true,
                        'establishment_type_id' => $hospitalType->id,
                        'establishment_id' => $hospital->id,
                        'distance_km' => 3.5,
                        'travel_time_minutes' => 15,
                    ],
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('establishments', [
            'name' => 'City Hospital',
            'establishment_type_id' => $hospitalType->id,
            'city_id' => $city->id,
        ]);

        $this->assertDatabaseHas('property_establishments', [
            'property_id' => $property->id,
            'distance_km' => 3.5,
            'travel_time_minutes' => 15,
        ]);
    }
}
