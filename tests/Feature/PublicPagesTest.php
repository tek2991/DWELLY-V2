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

    public function test_all_properties_supports_infinite_scroll_json_request()
    {
        $response = $this->getJson(route('allProperties'), [
            'X-Infinite-Scroll' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'html',
            'hasMore',
            'nextPage',
            'nextPageUrl',
            'total',
        ]);
        $response->assertJson([
            'hasMore' => false,
            'total' => 1,
        ]);
        $this->assertStringContainsString('Greenwood Heights', $response->json('html'));
    }

    public function test_all_properties_infinite_scroll_pagination_multiple_pages()
    {
        $existing = Property::first();

        // Create 11 additional listed properties so total is 12 (> 10 per page)
        for ($i = 2; $i <= 12; $i++) {
            $prop = Property::create([
                'code' => sprintf('GAU-%04d', $i),
                'building_name' => "Complex Unit {$i}",
                'address_line_1' => "Address {$i}",
                'locality_id' => $existing->locality_id,
                'locality' => $existing->locality,
                'city' => $existing->city,
                'bhk_type_id' => $existing->bhk_type_id,
                'property_type_id' => $existing->property_type_id,
                'status' => 'Vacant',
                'is_listed' => true,
            ]);

            PropertyPricingVersion::create([
                'property_id' => $prop->id,
                'effective_from' => now()->toDateString(),
                'rent' => 20000 + ($i * 1000),
                'security_deposit' => 40000,
            ]);
        }

        // Page 1
        $responsePage1 = $this->getJson(route('allProperties', ['page' => 1]), [
            'X-Infinite-Scroll' => '1',
        ]);
        $responsePage1->assertStatus(200);
        $responsePage1->assertJson([
            'hasMore' => true,
            'total' => 12,
            'nextPage' => 2,
        ]);

        // Page 2
        $responsePage2 = $this->getJson(route('allProperties', ['page' => 2]), [
            'X-Infinite-Scroll' => '1',
        ]);
        $responsePage2->assertStatus(200);
        $responsePage2->assertJson([
            'hasMore' => false,
            'total' => 12,
            'nextPage' => 3,
        ]);
    }

    public function test_property_code_is_shown_on_all_properties_and_view_property_in_development()
    {
        $property = Property::first();
        config(['app.show_property_code' => null]);
        $this->app->detectEnvironment(fn () => 'local');

        $allResponse = $this->get(route('allProperties'));
        $allResponse->assertStatus(200);
        $allResponse->assertSee($property->code);

        $ajaxResponse = $this->getJson(route('allProperties'), [
            'X-Infinite-Scroll' => '1',
        ]);
        $ajaxResponse->assertStatus(200);
        $this->assertStringContainsString($property->code, $ajaxResponse->json('html'));

        $viewResponse = $this->get(route('viewProperty', $property));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('ID: ' . $property->code);
    }

    public function test_property_code_is_hidden_on_all_properties_and_view_property_in_production()
    {
        $property = Property::first();
        config(['app.show_property_code' => null]);
        $this->app->detectEnvironment(fn () => 'production');

        $allResponse = $this->get(route('allProperties'));
        $allResponse->assertStatus(200);
        $allResponse->assertDontSee($property->code);

        $ajaxResponse = $this->getJson(route('allProperties'), [
            'X-Infinite-Scroll' => '1',
        ]);
        $ajaxResponse->assertStatus(200);
        $this->assertStringNotContainsString($property->code, $ajaxResponse->json('html'));

        $viewResponse = $this->get(route('viewProperty', $property));
        $viewResponse->assertStatus(200);
        $viewResponse->assertDontSee($property->code);
    }

    public function test_property_code_visibility_can_be_overridden_via_config_setting()
    {
        $property = Property::first();
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.show_property_code' => true]);

        $this->get(route('allProperties'))->assertSee($property->code);
        $this->get(route('viewProperty', $property))->assertSee('ID: ' . $property->code);

        $this->app->detectEnvironment(fn () => 'local');
        config(['app.show_property_code' => false]);

        $this->get(route('allProperties'))->assertDontSee($property->code);
        $this->get(route('viewProperty', $property))->assertDontSee($property->code);
    }

    public function test_all_properties_can_be_sorted_by_property_code()
    {
        $existing = Property::first();

        $propZ = Property::create([
            'code' => 'GAU-0999',
            'building_name' => 'Zeta Residency',
            'address_line_1' => 'Zoo Road',
            'locality_id' => $existing->locality_id,
            'locality' => $existing->locality,
            'city' => $existing->city,
            'bhk_type_id' => $existing->bhk_type_id,
            'property_type_id' => $existing->property_type_id,
            'status' => 'Vacant',
            'is_listed' => true,
        ]);

        $propA = Property::create([
            'code' => 'BLR-0001',
            'building_name' => 'Alpha Enclave',
            'address_line_1' => 'Indiranagar',
            'locality_id' => $existing->locality_id,
            'locality' => $existing->locality,
            'city' => $existing->city,
            'bhk_type_id' => $existing->bhk_type_id,
            'property_type_id' => $existing->property_type_id,
            'status' => 'Vacant',
            'is_listed' => true,
        ]);

        $response = $this->get(route('allProperties', ['sortBy' => 'code_asc']));
        $response->assertStatus(200);
        $response->assertSee('Property Code');
        $response->assertSeeInOrder([
            $propA->building_name,
            $existing->building_name,
            $propZ->building_name,
        ]);
    }
}
