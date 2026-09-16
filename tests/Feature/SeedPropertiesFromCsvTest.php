<?php

namespace Tests\Feature;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;
use App\Domain\Geographic\Models\Locality;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\OpportunitySource;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyCsvImportService;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\FinancialModelSeeder;
use Database\Seeders\OpportunitySourceSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RegionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tek2991\Accounting\Database\Seeders\IndianStatesSeeder;
use Tek2991\Accounting\Models\State;
use Tests\TestCase;

class SeedPropertiesFromCsvTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempCsvPath;
    protected string $tempSpecsCsvPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempCsvPath = storage_path('framework/testing/test_properties.csv');
        $this->tempSpecsCsvPath = storage_path('framework/testing/test_specs.csv');
        File::ensureDirectoryExists(dirname($this->tempCsvPath));

        // Seed basic reference data
        $this->seed(IndianStatesSeeder::class);
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(OpportunitySourceSeeder::class);
        $this->seed(FinancialModelSeeder::class);
        $this->seed(RegionsSeeder::class);

        // Ensure Bangalore exists
        $karnatakaState = State::where('name', 'Karnataka')->first();
        if ($karnatakaState) {
            $blrDistrict = District::firstOrCreate([
                'state_id' => $karnatakaState->id,
                'name' => 'Bangalore Urban',
            ], ['slug' => 'bangalore-urban', 'is_active' => true]);

            $blrCity = City::firstOrCreate([
                'district_id' => $blrDistrict->id,
                'name' => 'Bangalore',
            ], ['slug' => 'bangalore', 'is_active' => true]);

            Locality::firstOrCreate([
                'city_id' => $blrCity->id,
                'name' => 'Whitefield',
            ], ['slug' => 'whitefield', 'pincode' => '560066', 'is_active' => true]);
        }

        // Ensure Organization and Branches
        $org = \Tek2991\Accounting\Models\Organization::firstOrCreate(
            ['id' => 1],
            ['name' => 'Dwelly India', 'legal_name' => 'Dwelly Realtech Pvt Ltd', 'currency' => 'INR']
        );

        Branch::firstOrCreate(['id' => 1], [
            'organization_id' => $org->id,
            'name' => 'Guwahati Head Office',
            'code' => 'GHY',
            'city' => 'Guwahati',
            'is_active' => true,
        ]);
        Branch::firstOrCreate(['id' => 2], [
            'organization_id' => $org->id,
            'name' => 'Bangalore Branch',
            'code' => 'BLR',
            'city' => 'Bangalore',
            'is_active' => true,
        ]);

        // Ensure Admin user
        User::firstOrCreate(['email' => 'admin@dwelly.in'], ['name' => 'Dwelly Admin', 'password' => bcrypt('password')]);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempCsvPath)) {
            File::delete($this->tempCsvPath);
        }
        if (File::exists($this->tempSpecsCsvPath)) {
            File::delete($this->tempSpecsCsvPath);
        }

        parent::tearDown();
    }

    public function test_dry_run_validates_existing_template_csv_without_errors(): void
    {
        $this->artisan('dwelly:seed-properties-csv', [
            '--file' => 'database/seeders/data/existing_properties_template.csv',
            '--dry-run' => true,
        ])
        ->expectsOutputToContain('Dry run validation succeeded for 5 rows.')
        ->assertExitCode(0);
    }

    public function test_validation_fails_on_missing_required_headers(): void
    {
        File::put($this->tempCsvPath, "property_code,some_field\nPROP-01,Test");

        $service = app(PropertyCsvImportService::class);
        $result = $service->import($this->tempCsvPath, true);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required column header', $result['errors'][0]);
    }

    public function test_validation_fails_when_occupied_property_lacks_tenant(): void
    {
        $csvContent = implode(',', [
            'property_code', 'building_name', 'address_line_1', 'address_line_2', 'locality', 'city', 'state', 'pincode',
            'bhk_type', 'property_type', 'furnishing_type', 'floor', 'total_floors', 'floor_space_sqft', 'property_status',
            'is_listed', 'available_from', 'owner_name', 'owner_phone', 'owner_email', 'owner_pan', 'owner_aadhaar',
            'owner_bank_name', 'owner_bank_account', 'owner_bank_ifsc', 'mou_number', 'mou_start_date', 'mou_status',
            'mou_fee_percentage', 'rent_amount', 'security_deposit', 'society_fee', 'mou_pdf_file', 'tenant_name',
            'tenant_phone', 'tenant_email', 'tenant_pan', 'tenant_aadhaar', 'agreement_code', 'agreement_start_date',
            'agreement_end_date', 'lock_in_months', 'notice_period_days', 'agreement_pdf_file', 'photo_files'
        ]) . "\n";

        $csvContent .= 'TEST-001,Test Heights,"123 Main St",,Zoo Road,Guwahati,Assam,781024,2 BHK,Apartment,Semi-Furnished,2,5,1000,Occupied,true,2026-01-01,Owner Name,9999911111,owner@test.com,,,,,,,,25000,50000,,,,,,' . "\n";

        File::put($this->tempCsvPath, $csvContent);

        $service = app(PropertyCsvImportService::class);
        $result = $service->import($this->tempCsvPath, true);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString("'tenant_name' is required when property_status is 'Occupied'", implode(' ', $result['errors']));
    }

    public function test_can_seed_properties_mous_agreements_and_photos(): void
    {
        $csvContent = implode(',', [
            'property_code', 'building_name', 'address_line_1', 'address_line_2', 'locality', 'city', 'state', 'pincode',
            'bhk_type', 'property_type', 'furnishing_type', 'floor', 'total_floors', 'floor_space_sqft', 'property_status',
            'is_listed', 'available_from', 'owner_name', 'owner_phone', 'owner_email', 'owner_pan', 'owner_aadhaar',
            'owner_bank_name', 'owner_bank_account', 'owner_bank_ifsc', 'mou_number', 'mou_start_date', 'mou_status',
            'mou_fee_percentage', 'rent_amount', 'security_deposit', 'society_fee', 'mou_pdf_file', 'tenant_name',
            'tenant_phone', 'tenant_email', 'tenant_pan', 'tenant_aadhaar', 'agreement_code', 'agreement_start_date',
            'agreement_end_date', 'lock_in_months', 'notice_period_days', 'agreement_pdf_file', 'photo_files'
        ]) . "\n";

        // Occupied Property
        $csvContent .= 'TEST-OCC-01,Orchid Residency,"Flat 1A, Zoo Road",,Zoo Road,Guwahati,Assam,781024,2 BHK,Apartment,Semi-Furnished,1,4,1100,Occupied,true,2026-01-01,Mr Owner,9876500001,owner.orchid@test.com,PANOW0001Z,987654321099,HDFC Bank,50100999991,HDFC0000084,MOU-TEST-01,2025-10-01,converted,8.0,22000,44000,1500,,Mr Tenant,9876500002,tenant.orchid@test.com,PANTN0002X,123456789099,TNC-TEST-01,2026-01-01,2026-12-31,6,30,,photos/orchid_ext.jpg' . "\n";

        // Vacant Property
        $csvContent .= 'TEST-VAC-01,Palm Grove Villa,"Villa 5, Whitefield",,Whitefield,Bangalore,Karnataka,560066,3 BHK,Villa,Fully Furnished,1,2,2200,Vacant,true,2026-02-01,Ms Owner,9876500003,owner.palm@test.com,PANOW0002Y,987654321088,ICICI Bank,00020999992,ICIC0000002,MOU-TEST-02,2025-11-01,converted,8.0,60000,180000,4000,,,,,,,,,,,photos/palm_villa.jpg' . "\n";

        File::put($this->tempCsvPath, $csvContent);

        $this->artisan('dwelly:seed-properties-csv', [
            '--file' => $this->tempCsvPath,
        ])
        ->expectsOutputToContain('Successfully imported 2 of 2 properties.')
        ->assertExitCode(0);

        // 1. Verify Occupied Property
        $propOcc = Property::where('code', 'TEST-OCC-01')->first();
        $this->assertNotNull($propOcc);
        $this->assertEquals('Orchid Residency', $propOcc->building_name);
        $this->assertEquals('Occupied', $propOcc->status);
        $this->assertEquals('Guwahati', $propOcc->city);

        // Verify MOU & Spatie Media
        $mouOcc = Mou::where('property_id', $propOcc->id)->first();
        $this->assertNotNull($mouOcc);
        $this->assertEquals('MOU-TEST-01', $mouOcc->number);
        $this->assertTrue($mouOcc->hasMedia('signed_pdf'));

        // Verify Tenancy Agreement & Role & Spatie Media
        $agreementOcc = TenancyAgreement::where('property_id', $propOcc->id)->first();
        $this->assertNotNull($agreementOcc);
        $this->assertEquals('TNC-TEST-01', $agreementOcc->code);
        $this->assertTrue($agreementOcc->hasMedia('signed_agreement'));
        $this->assertEquals('Mr Tenant', $agreementOcc->primaryTenant?->party?->display_name);

        // Verify Photos
        $this->assertGreaterThan(0, $propOcc->photos()->count());

        // 2. Verify Vacant Property
        $propVac = Property::where('code', 'TEST-VAC-01')->first();
        $this->assertNotNull($propVac);
        $this->assertEquals('Palm Grove Villa', $propVac->building_name);
        $this->assertEquals('Vacant', $propVac->status);
        $this->assertEquals('Bangalore', $propVac->city);

        // Verify Vacant Property has no tenancy agreement
        $this->assertEquals(0, $propVac->agreements()->count());

        // Verify Owner Party
        $ownerVac = Party::where('email', 'owner.palm@test.com')->first();
        $this->assertNotNull($ownerVac);
        $this->assertEquals('Ms Owner', $ownerVac->display_name);
    }

    public function test_can_auto_discover_mou_tenancy_and_photos_from_property_code_folder(): void
    {
        $propFolder = storage_path('app/seed_assets/properties/TEST-DIR-01');
        $photosFolder = "{$propFolder}/photos";
        File::ensureDirectoryExists($photosFolder);

        File::put("{$propFolder}/mou.pdf", '%PDF-1.4 mock mou');
        File::put("{$propFolder}/tenancy.pdf", '%PDF-1.4 mock tenancy');
        File::put("{$photosFolder}/01_elevation.jpg", 'mock image 1');
        File::put("{$photosFolder}/02_living.jpg", 'mock image 2');

        // CSV without any file path columns
        $csvContent = implode(',', [
            'property_code', 'building_name', 'address_line_1', 'address_line_2', 'locality', 'city', 'state', 'pincode',
            'bhk_type', 'property_type', 'furnishing_type', 'floor', 'total_floors', 'floor_space_sqft', 'property_status',
            'is_listed', 'available_from', 'owner_name', 'owner_phone', 'owner_email', 'owner_pan', 'owner_aadhaar',
            'owner_bank_name', 'owner_bank_account', 'owner_bank_ifsc', 'mou_number', 'mou_start_date', 'mou_status',
            'mou_fee_percentage', 'rent_amount', 'security_deposit', 'society_fee', 'tenant_name', 'tenant_phone',
            'tenant_email', 'tenant_pan', 'tenant_aadhaar', 'agreement_code', 'agreement_start_date', 'agreement_end_date',
            'lock_in_months', 'notice_period_days'
        ]) . "\n";

        $csvContent .= 'TEST-DIR-01,Folder Test Tower,"Flat 10A, Zoo Road",,Zoo Road,Guwahati,Assam,781024,2 BHK,Apartment,Semi-Furnished,10,12,1200,Occupied,true,2026-01-01,Folder Owner,9876540001,folder.owner@test.com,PANFO0001Z,987654329999,HDFC Bank,50100888881,HDFC0000084,MOU-DIR-01,2025-10-01,converted,8.0,30000,60000,2500,Folder Tenant,9876540002,folder.tenant@test.com,PANTN9999X,123456789999,TNC-DIR-01,2026-01-01,2026-12-31,6,30' . "\n";

        File::put($this->tempCsvPath, $csvContent);

        $this->artisan('dwelly:seed-properties-csv', [
            '--file' => $this->tempCsvPath,
        ])
        ->expectsOutputToContain('Successfully imported 1 of 1 properties.')
        ->assertExitCode(0);

        $prop = Property::where('code', 'TEST-DIR-01')->first();
        $this->assertNotNull($prop);

        // Verify MOU picked up mou.pdf from folder
        $mou = Mou::where('property_id', $prop->id)->first();
        $this->assertNotNull($mou);
        $this->assertEquals('mou.pdf', $mou->getFirstMedia('signed_pdf')?->file_name);

        // Verify Tenancy Agreement picked up tenancy.pdf from folder
        $agreement = TenancyAgreement::where('property_id', $prop->id)->first();
        $this->assertNotNull($agreement);
        $this->assertEquals('tenancy.pdf', $agreement->getFirstMedia('signed_agreement')?->file_name);

        // Verify Photos picked up all images from photos/ folder
        $photos = $prop->photos()->orderBy('order_column')->get();
        $this->assertCount(2, $photos);
        $this->assertTrue($photos[0]->is_featured);
        $this->assertStringContainsString('01_elevation.jpg', $photos[0]->file_path);
        $this->assertFalse($photos[1]->is_featured);
        $this->assertStringContainsString('02_living.jpg', $photos[1]->file_path);

        // Clean up test folder
        File::deleteDirectory($propFolder);
    }

    public function test_can_seed_property_specifications_from_separate_csv(): void
    {
        // 1. Property CSV
        $propCsv = implode(',', [
            'property_code', 'building_name', 'address_line_1', 'address_line_2', 'locality', 'city', 'state', 'pincode',
            'bhk_type', 'property_type', 'furnishing_type', 'floor', 'total_floors', 'floor_space_sqft', 'property_status',
            'is_listed', 'available_from', 'owner_name', 'owner_phone', 'owner_email', 'owner_pan', 'owner_aadhaar',
            'owner_bank_name', 'owner_bank_account', 'owner_bank_ifsc', 'mou_number', 'mou_start_date', 'mou_status',
            'mou_fee_percentage', 'rent_amount', 'security_deposit', 'society_fee'
        ]) . "\n";

        $propCsv .= 'TEST-SPEC-01,Royal Palm,"Flat 301, Zoo Road",,Zoo Road,Guwahati,Assam,781024,3 BHK,Apartment,Semi-Furnished,3,6,1500,Vacant,true,2026-01-01,Spec Owner,9876540010,spec.owner@test.com,PANS0001Z,987654320010,HDFC Bank,50100888882,HDFC0000084,MOU-SPEC-01,2025-10-01,converted,8.0,35000,70000,2000' . "\n";
        File::put($this->tempCsvPath, $propCsv);

        // 2. Separate Specifications CSV
        $specsCsv = implode(',', [
            'property_code', 'room_living_room', 'room_kitchen', 'room_master_bedroom', 'room_second_bedroom',
            'room_third_bedroom', 'room_guest_bedroom', 'room_attached_bathroom', 'room_common_bathroom',
            'room_balcony', 'room_pooja_room', 'room_servant_room', 'room_study_room',
            'amenity_lift', 'amenity_power_backup', 'amenity_security', 'amenity_parking', 'amenity_gym', 'amenity_swimming_pool', 'amenity_clubhouse',
            'inv_fan', 'inv_light', 'inv_ac', 'inv_bed', 'inv_wardrobe', 'inv_sofa', 'inv_dining_set', 'inv_geyser', 'inv_fridge', 'inv_tv', 'inv_washing_machine', 'inv_microwave', 'inv_water_purifier', 'inv_chimney', 'inv_keys'
        ]) . "\n";

        $specsCsv .= 'TEST-SPEC-01,1,1,1,1,1,0,2,1,2,yes,no,no,yes,yes,yes,yes,no,no,no,5,10,3,3,3,1,1,2,0,0,0,0,0,1,4' . "\n";
        File::put($this->tempSpecsCsvPath, $specsCsv);

        $this->artisan('dwelly:seed-properties-csv', [
            '--file' => $this->tempCsvPath,
            '--specs-file' => $this->tempSpecsCsvPath,
        ])
        ->expectsOutputToContain('Successfully imported 1 of 1 properties.')
        ->assertExitCode(0);

        $prop = Property::where('code', 'TEST-SPEC-01')->first();
        $this->assertNotNull($prop);

        // Verify custom rooms generated from specs CSV:
        // 1 living + 1 kitchen + 1 master + 1 second + 1 third + 2 att bath + 1 com bath + 2 balcony + 1 pooja = 11
        $this->assertEquals(11, $prop->rooms()->count());
        $this->assertTrue($prop->rooms()->where('custom_name', 'Pooja Room')->exists());
        $this->assertEquals(2, $prop->rooms()->where('custom_name', 'LIKE', 'Front Balcony%')->count());
        $this->assertEquals(2, $prop->rooms()->where('custom_name', 'LIKE', 'Attached Bathroom%')->count());

        // Verify amenities: 4 active (Lift, Power Backup, Security, Parking)
        $this->assertEquals(4, $prop->amenities()->count());

        // Verify inventory: keys = 4, fans = 5, ac = 3
        $invKeys = $prop->inventories()->whereHas('inventoryType', fn ($q) => $q->where('slug', 'keys'))->first();
        $this->assertNotNull($invKeys);
        $this->assertEquals(4, $invKeys->count);

        $invFans = $prop->inventories()->whereHas('inventoryType', fn ($q) => $q->where('slug', 'fan'))->first();
        $this->assertNotNull($invFans);
        $this->assertEquals(5, $invFans->count);
    }

    public function test_can_update_property_specifications_standalone_via_artisan_command(): void
    {
        // 1. Create a base property
        $propCsv = implode(',', [
            'property_code', 'building_name', 'address_line_1', 'address_line_2', 'locality', 'city', 'state', 'pincode',
            'bhk_type', 'property_type', 'furnishing_type', 'floor', 'total_floors', 'floor_space_sqft', 'property_status',
            'is_listed', 'available_from', 'owner_name', 'owner_phone', 'owner_email', 'owner_pan', 'owner_aadhaar',
            'owner_bank_name', 'owner_bank_account', 'owner_bank_ifsc', 'mou_number', 'mou_start_date', 'mou_status',
            'mou_fee_percentage', 'rent_amount', 'security_deposit', 'society_fee'
        ]) . "\n";

        $propCsv .= 'TEST-SPEC-STANDALONE,Silver Crest,"Flat 101, Zoo Road",,Zoo Road,Guwahati,Assam,781024,2 BHK,Apartment,Unfurnished,1,4,1000,Vacant,true,2026-01-01,Standalone Owner,9876540020,stand.owner@test.com,PANST0001Z,987654320020,HDFC Bank,50100888883,HDFC0000084,MOU-STAND-01,2025-10-01,converted,8.0,20000,40000,1000' . "\n";
        File::put($this->tempCsvPath, $propCsv);

        $this->artisan('dwelly:seed-properties-csv', [
            '--file' => $this->tempCsvPath,
        ])->assertExitCode(0);

        $prop = Property::where('code', 'TEST-SPEC-STANDALONE')->first();
        $this->assertNotNull($prop);

        // 2. Prepare Specs CSV with custom counts
        $specsCsv = implode(',', [
            'property_code', 'room_living_room', 'room_kitchen', 'room_master_bedroom', 'room_second_bedroom',
            'room_attached_bathroom', 'room_common_bathroom', 'room_balcony', 'room_study_room',
            'amenity_lift', 'amenity_power_backup', 'amenity_gym',
            'inv_fan', 'inv_light', 'inv_ac', 'inv_keys'
        ]) . "\n";

        $specsCsv .= 'TEST-SPEC-STANDALONE,1,1,1,1,1,1,1,1,yes,yes,yes,4,8,2,5' . "\n";
        File::put($this->tempSpecsCsvPath, $specsCsv);

        // Test dry-run
        $this->artisan('dwelly:seed-property-specs', [
            '--file' => $this->tempSpecsCsvPath,
            '--dry-run' => true,
        ])
        ->expectsOutputToContain('Dry-run validated specifications for 1 properties.')
        ->assertExitCode(0);

        // Test live update
        $this->artisan('dwelly:seed-property-specs', [
            '--file' => $this->tempSpecsCsvPath,
        ])
        ->expectsOutputToContain('Successfully updated specifications for 1 properties.')
        ->assertExitCode(0);

        $prop->refresh();
        $this->assertEquals(8, $prop->rooms()->count());
        $this->assertTrue($prop->rooms()->where('custom_name', 'Office Room')->exists());
        $this->assertEquals(3, $prop->amenities()->count());

        $invKeys = $prop->inventories()->whereHas('inventoryType', fn ($q) => $q->where('slug', 'keys'))->first();
        $this->assertNotNull($invKeys);
        $this->assertEquals(5, $invKeys->count);
    }
}
