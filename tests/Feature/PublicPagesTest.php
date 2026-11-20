<?php

namespace Tests\Feature;

use App\Domain\Communication\Models\PublicInquiry;
use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;
use App\Domain\Geographic\Models\Locality;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Property\Models\BhkType;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyPhoto;
use App\Domain\Property\Models\PropertyPricingVersion;
use App\Domain\Property\Models\PropertyType;
use App\Livewire\BookPropertyModal;
use App\Livewire\ContactForm;
use App\Livewire\RentOutModal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $stateId = DB::table('acc_states')->insertGetId([
            'country_id' => 'IN',
            'name' => 'Assam',
            'code' => 'AS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $district = District::create([
            'state_id' => $stateId,
            'name' => 'Kamrup Metro',
            'slug' => 'kamrup-metro',
            'is_active' => true,
        ]);

        $city = City::create([
            'district_id' => $district->id,
            'name' => 'Guwahati',
            'slug' => 'guwahati',
            'is_active' => true,
        ]);

        $locality = Locality::create([
            'city_id' => $city->id,
            'name' => 'Zoo Road',
            'slug' => 'zoo-road',
            'is_active' => true,
        ]);

        $bhk = BhkType::create([
            'name' => '2 BHK',
            'slug' => '2-bhk',
            'is_active' => true,
        ]);

        $propType = PropertyType::create([
            'name' => 'Apartment',
            'slug' => 'apartment',
            'is_active' => true,
        ]);

        $property = Property::create([
            'code' => 'GAU-0001',
            'building_name' => 'Greenwood Heights',
            'address_line_1' => 'Zoo Road Tiniali',
            'locality_id' => $locality->id,
            'locality' => 'Zoo Road',
            'city' => 'Guwahati',
            'bhk_type_id' => $bhk->id,
            'property_type_id' => $propType->id,
            'status' => 'Vacant',
            'is_listed' => true,
            'floor' => 2,
            'total_floors' => 5,
            'floor_space_sqft' => 1100,
        ]);

        PropertyPricingVersion::create([
            'property_id' => $property->id,
            'effective_from' => now()->toDateString(),
            'rent' => 25000,
            'security_deposit' => 50000,
            'society_fee' => 2000,
            'booking_amount' => 10000,
        ]);

        PropertyPhoto::create([
            'property_id' => $property->id,
            'file_path' => 'property-photos/test_front.jpeg',
            'title' => 'Front Photo',
            'is_featured' => true,
            'is_visible' => true,
            'order_column' => 1,
        ]);
    }

    public function test_home_page_renders_successfully()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('Want a place to Stay?');
        $response->assertSee('Greenwood Heights');
        $response->assertSee('2 BHK');
        $response->assertSee('25,000');
    }

    public function test_all_properties_page_renders_successfully()
    {
        $response = $this->get(route('allProperties'));
        $response->assertStatus(200);
        $response->assertSee('Filters');
        $response->assertSee('Greenwood Heights');
    }

    public function test_about_page_renders_successfully()
    {
        $response = $this->get(route('about'));
        $response->assertStatus(200);
        $response->assertSee('Team');
        $response->assertSee('Kaushal Choudhury');
    }

    public function test_contact_page_renders_successfully()
    {
        $response = $this->get(route('contact'));
        $response->assertStatus(200);
        $response->assertSee('Contact Us');
        $response->assertSee('hello@dwelly.in');
    }

    public function test_owners_page_renders_successfully()
    {
        $response = $this->get(route('owners'));
        $response->assertStatus(200);
        $response->assertSee('RENT OUT WITH DWELLY');
    }

    public function test_view_property_page_renders_successfully()
    {
        $property = Property::first();
        $response = $this->get(route('viewProperty', $property));
        $response->assertStatus(200);
        $response->assertSee('BOOK A VISIT');
        $response->assertSee('Greenwood Heights');
    }

    public function test_property_brochure_pdf_generates_successfully()
    {
        $property = Property::first();
        $response = $this->get(route('propertyBrocure', $property));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_contact_form_submits_successfully()
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'John Doe')
            ->set('phone', '9876543210')
            ->set('email', 'john@example.com')
            ->set('message', 'Interested in rentals')
            ->call('submit')
            ->assertSet('success', true);

        $this->assertDatabaseHas('public_inquiries', [
            'type' => 'general',
            'name' => 'John Doe',
            'phone' => '9876543210',
            'email' => 'john@example.com',
        ]);
    }

    public function test_book_property_modal_submits_successfully()
    {
        $property = Property::first();

        Livewire::test(BookPropertyModal::class)
            ->dispatch('open-book-modal', property_id: $property->id)
            ->assertSet('isOpen', true)
            ->assertSet('property_id', $property->id)
            ->set('name', 'Jane Doe')
            ->set('phone', '9123456789')
            ->set('email', 'jane@example.com')
            ->set('message', 'Can I visit tomorrow?')
            ->call('submit')
            ->assertSet('success', true);

        $this->assertDatabaseHas('public_inquiries', [
            'type' => 'booking',
            'property_id' => $property->id,
            'name' => 'Jane Doe',
            'phone' => '9123456789',
        ]);
    }

    public function test_rent_out_modal_submits_and_creates_opportunity()
    {
        $propType = PropertyType::first();

        Livewire::test(RentOutModal::class)
            ->dispatch('open-rent-modal')
            ->assertSet('isOpen', true)
            ->set('name', 'Property Owner')
            ->set('phone', '9988776655')
            ->set('email', 'owner@example.com')
            ->set('num_of_properties', 1)
            ->set('building_name.0', 'Sunrise Villa')
            ->set('address.0', 'Ulubari, Guwahati')
            ->set('bedroom.0', 3)
            ->set('bathroom.0', 2)
            ->set('property_type.0', $propType->id)
            ->call('submit')
            ->assertSet('success', true);

        $this->assertDatabaseHas('public_inquiries', [
            'type' => 'rent_out',
            'name' => 'Property Owner',
            'phone' => '9988776655',
        ]);

        $this->assertDatabaseHas('opportunities', [
            'owner_name' => 'Property Owner',
            'owner_phone' => '9988776655',
            'estimated_bhk' => '3 BHK',
        ]);
    }
}
