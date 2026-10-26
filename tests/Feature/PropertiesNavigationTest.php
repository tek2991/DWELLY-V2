<?php

namespace Tests\Feature;

use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\Property;
use App\Filament\Clusters\OnboardingsCluster;
use App\Filament\Pages\Properties\OnboardingQueue;
use App\Filament\Pages\Properties\ReviewQueue;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertiesNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_resource_has_correct_navigation_properties(): void
    {
        $this->assertNull(PropertyResource::getCluster());
        $this->assertEquals('Properties', PropertyResource::getNavigationLabel());
        $this->assertEquals('Properties & Leasing', PropertyResource::getNavigationGroup());
        $this->assertEquals(2, PropertyResource::getNavigationSort());
    }

    public function test_onboardings_cluster_has_correct_navigation_properties(): void
    {
        $this->assertEquals('Onboardings', OnboardingsCluster::getNavigationLabel());
        $this->assertEquals('Properties & Leasing', OnboardingsCluster::getNavigationGroup());
        $this->assertEquals(3, OnboardingsCluster::getNavigationSort());
    }

    public function test_onboarding_queue_belongs_to_onboardings_cluster(): void
    {
        $this->assertEquals(OnboardingsCluster::class, OnboardingQueue::getCluster());
        $this->assertEquals('Onboarding Queue', OnboardingQueue::getNavigationLabel());
    }

    public function test_review_queue_belongs_to_onboardings_cluster(): void
    {
        $this->assertEquals(OnboardingsCluster::class, ReviewQueue::getCluster());
        $this->assertEquals('Review Queue', ReviewQueue::getNavigationLabel());
    }

    public function test_authenticated_user_can_access_onboarding_queue_page(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'code' => 'PROP-101',
            'building_name' => 'Royal Palms',
            'status' => 'Onboarding',
        ]);

        OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Draft',
        ]);

        $this->actingAs($user);

        Livewire::test(OnboardingQueue::class)
            ->assertSuccessful()
            ->assertSee('PROP-101');
    }

    public function test_authenticated_user_can_access_review_queue_page(): void
    {
        $reviewer = User::factory()->create();

        $property = Property::create([
            'code' => 'PROP-REV-100',
            'building_name' => 'Review Queue Test Property',
            'status' => 'Onboarding',
        ]);

        OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Pending Review',
            'submitted_at' => now(),
        ]);

        $this->actingAs($reviewer);

        Livewire::test(ReviewQueue::class)
            ->assertSuccessful()
            ->assertSee('PROP-REV-100');
    }

    public function test_properties_list_page_renders_with_tabs(): void
    {
        $user = User::factory()->create();

        Property::create([
            'code' => 'PROP-VAC-1',
            'building_name' => 'Vacant House',
            'status' => 'Vacant',
        ]);

        Property::create([
            'code' => 'PROP-OCC-1',
            'building_name' => 'Occupied Flat',
            'status' => 'Occupied',
        ]);

        Property::create([
            'code' => 'PROP-ONB-1',
            'building_name' => 'Onboarding Villa',
            'status' => 'Onboarding',
        ]);

        $this->actingAs($user);

        Livewire::test(ListProperties::class)
            ->assertSuccessful()
            ->assertSee('Vacant House')
            ->assertSee('Occupied Flat')
            ->assertSee('Onboarding Villa')
            ->set('activeTab', 'vacant')
            ->assertSee('Vacant House')
            ->assertDontSee('Occupied Flat')
            ->set('activeTab', 'occupied')
            ->assertSee('Occupied Flat')
            ->assertDontSee('Vacant House');
    }
}
