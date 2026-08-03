<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Property\Models\Property;

class TenancyAgreementSecondaryTenantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_and_retrieve_secondary_tenants_on_tenancy_agreement(): void
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

        $locality = \App\Domain\Geographic\Models\Locality::create([
            'city_id' => $city->id,
            'name' => 'Zoo Road',
            'slug' => 'zoo-road',
        ]);

        $property = Property::create([
            'locality_id' => $locality->id,
            'building_name' => 'Green Heights Apartment',
            'address_line_1' => 'Zoo Road Tiniali',
            'pincode' => '781024',
            'status' => 'vacant',
        ]);

        $secondaryTenantsData = [
            [
                'name' => 'Sunita Sharma',
                'relationship' => 'Spouse',
                'phone' => '9876543210',
                'email' => 'sunita@example.com',
                'aadhaar_number' => '1234-5678-9012',
                'pan_number' => 'ABCDE1234F',
                'voter_id' => 'VTR9876543',
                'photo_file' => 'tenancy-secondary-kyc/sunita_photo.jpg',
                'aadhaar_file' => 'tenancy-secondary-kyc/sunita_aadhaar.pdf',
                'pan_file' => 'tenancy-secondary-kyc/sunita_pan.jpg',
                'voter_id_file' => 'tenancy-secondary-kyc/sunita_voter.jpg',
                'other_kyc_files' => [
                    'tenancy-secondary-kyc/photo.png',
                ],
            ],
            [
                'name' => 'Rohan Sharma',
                'relationship' => 'Son',
                'phone' => '9876543211',
                'email' => 'rohan@example.com',
                'aadhaar_number' => '9876-5432-1098',
                'pan_number' => null,
                'voter_id' => null,
                'aadhaar_file' => 'tenancy-secondary-kyc/rohan_aadhaar.pdf',
                'pan_file' => null,
                'voter_id_file' => null,
                'other_kyc_files' => [],
            ],
        ];

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => 'TA-TEST-001',
            'status' => 'draft',
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'rent_amount' => 25000,
            'security_deposit' => 50000,
            'booking_amount' => 5000,
            'lock_in_period_months' => 6,
            'notice_period_days' => 30,
            'first_month_rent' => 25000,
            'secondary_tenants' => $secondaryTenantsData,
        ]);

        $this->assertDatabaseHas('tenancy_agreements', [
            'id' => $agreement->id,
            'code' => 'TA-TEST-001',
        ]);

        $freshAgreement = TenancyAgreement::find($agreement->id);

        $this->assertIsArray($freshAgreement->secondary_tenants);
        $this->assertCount(2, $freshAgreement->secondary_tenants);
        $this->assertEquals('Sunita Sharma', $freshAgreement->secondary_tenants[0]['name']);
        $this->assertEquals('Spouse', $freshAgreement->secondary_tenants[0]['relationship']);
        $this->assertEquals('1234-5678-9012', $freshAgreement->secondary_tenants[0]['aadhaar_number']);
        $this->assertEquals('tenancy-secondary-kyc/sunita_aadhaar.pdf', $freshAgreement->secondary_tenants[0]['aadhaar_file']);
        $this->assertEquals('tenancy-secondary-kyc/sunita_pan.jpg', $freshAgreement->secondary_tenants[0]['pan_file']);
        $this->assertEquals('tenancy-secondary-kyc/sunita_voter.jpg', $freshAgreement->secondary_tenants[0]['voter_id_file']);
        $this->assertCount(1, $freshAgreement->secondary_tenants[0]['other_kyc_files']);
        $this->assertEquals('Rohan Sharma', $freshAgreement->secondary_tenants[1]['name']);
        $this->assertEquals('Son', $freshAgreement->secondary_tenants[1]['relationship']);
    }
}
