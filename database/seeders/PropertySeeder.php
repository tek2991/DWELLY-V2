<?php

namespace Database\Seeders;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\Locality;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunitySource;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\VendorProfile;
use App\Domain\Party\Models\VendorTrade;
use App\Domain\Property\Models\AmenityType;
use App\Domain\Property\Models\Establishment;
use App\Domain\Property\Models\FurnishingType;
use App\Domain\Property\Models\InventoryType;
use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyAmenity;
use App\Domain\Property\Models\PropertyEstablishment;
use App\Domain\Property\Models\PropertyFinancialTerm;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyPhoto;
use App\Domain\Property\Models\PropertyPricingVersion;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyType;
use App\Domain\Property\Models\PropertyUtility;
use App\Domain\Property\Models\RoomDefinition;
use App\Domain\Property\Models\UtilityType;
use App\Models\Branch;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertySeeder extends Seeder
{
    protected ?User $adminUser = null;
    protected ?User $executiveUser = null;
    protected ?User $reviewerUser = null;

    protected array $cities = [];
    protected array $localities = [];
    protected array $propertyTypes = [];
    protected array $bhkTypes = [];
    protected array $furnishingTypes = [];
    protected array $flooringTypes = [];
    protected array $financialModels = [];
    protected array $opportunitySources = [];
    protected array $establishments = [];
    protected array $utilityTypes = [];
    protected array $roomDefinitions = [];
    protected array $inventoryTypes = [];
    protected array $amenityTypes = [];
    protected array $vendorTrades = [];
    protected array $branches = [];

    public function run(): void
    {
        $this->command->info('Starting PropertySeeder: preparing reference data and users...');

        $this->setupUsers();
        $this->setupGeographicData();
        $this->loadReferenceData();

        $this->command->info('Seeding Owner Parties, Tenant Parties & Vendor Parties...');
        $owners = $this->seedOwners();
        $tenants = $this->seedTenants();
        $vendors = $this->seedVendors();

        $this->command->info('Defining 50 Property specifications...');
        $propertyDefinitions = $this->getPropertyDefinitions();

        $this->command->info('Generating DomPDF base MOU template...');
        $basePdfContent = $this->generateBasePdfContent();

        $this->command->info('Seeding 50 Properties with Opportunities, MOUs, Onboarding & Units...');
        $progressBar = $this->command->getOutput()->createProgressBar(count($propertyDefinitions));
        $progressBar->start();

        foreach ($propertyDefinitions as $index => $def) {
            DB::transaction(function () use ($def, $index, $owners, $tenants, $basePdfContent) {
                $owner = $owners[$def['owner_index'] % count($owners)];
                $branchId = $this->branches[$def['city_name']] ?? 1;

                // 1. Create or Find Property
                $property = Property::firstOrNew(['code' => $def['code']]);
                $property->fill([
                    'branch_id' => $branchId,
                    'code' => $def['code'],
                    'building_name' => $def['building_name'],
                    'address_line_1' => $def['address_line_1'],
                    'address_line_2' => $def['address_line_2'] ?? null,
                    'city' => $def['city_name'],
                    'locality_id' => $def['locality_id'],
                    'pincode' => $def['pincode'],
                    'landmark' => $def['landmark'] ?? null,
                    'latitude' => $def['latitude'],
                    'longitude' => $def['longitude'],
                    'property_type_id' => $def['property_type_id'],
                    'bhk_type_id' => $def['bhk_type_id'],
                    'floor' => $def['floor'],
                    'total_floors' => $def['total_floors'],
                    'floor_space_sqft' => $def['sqft'],
                    'flooring_type_id' => $def['flooring_type_id'],
                    'furnishing_type_id' => $def['furnishing_type_id'],
                    'status' => $def['property_status'],
                    'is_listed' => $def['is_listed'] ?? true,
                    'is_promoted' => $def['is_promoted'] ?? false,
                    'available_from' => $def['available_from'] ?? now()->toDateString(),
                    'assigned_executive_id' => $this->executiveUser->id,
                ]);
                $property->save();

                // 2. Create Opportunity
                $oppNumber = sprintf('OPP-2026-%05d', $index + 2);
                $opportunity = Opportunity::firstOrCreate(
                    ['number' => $oppNumber],
                    [
                        'branch_id' => $branchId,
                        'title' => "{$def['bhk_name']} at {$def['building_name']} - {$owner->display_name}",
                        'status' => 'converted',
                        'opportunity_source_id' => $this->opportunitySources[array_rand($this->opportunitySources)],
                        'assigned_user_id' => $this->executiveUser->id,
                        'owner_party_id' => $owner->id,
                        'owner_name' => $owner->display_name,
                        'owner_phone' => $owner->phone,
                        'owner_email' => $owner->email,
                        'address' => $def['address_line_1'],
                        'estimated_property_type_id' => $def['property_type_id'],
                        'estimated_bhk' => $def['bhk_name'],
                        'estimated_size' => $def['sqft'],
                        'estimated_is_furnished' => str_contains(strtolower($def['furnishing_name']), 'furnished'),
                        'expected_rent' => $def['rent'],
                        'expected_financial_model_id' => $this->financialModels['Rent share'] ?? array_values($this->financialModels)[0],
                    ]
                );

                // 3. Create MOU linking Property to Owner Party
                $mouNumber = sprintf('MOU-2026-%05d', $index + 2);
                $mou = Mou::firstOrNew(['number' => $mouNumber]);
                $mou->fill([
                    'branch_id' => $branchId,
                    'opportunity_id' => $opportunity->id,
                    'property_id' => $property->id,
                    'party_id' => $owner->id,
                    'type' => MouType::ONBOARDING,
                    'status' => MouStatus::CONVERTED,
                    'version' => 1,
                    'start_date' => now()->subMonths(3)->toDateString(),
                    'owner_details' => [
                        'party_id' => $owner->id,
                        'party_type' => 'individual',
                        'name' => $owner->display_name,
                        'phone' => $owner->phone,
                        'email' => $owner->email,
                        'pan_number' => $owner->individual?->pan_number ?? 'ABCDE1234F',
                        'aadhar_number' => $owner->individual?->aadhaar_number ?? '987654321012',
                        'address' => $def['address_line_1'],
                        'state' => $def['city_name'] === 'Guwahati' ? 'Assam' : 'Karnataka',
                    ],
                    'signatory_details' => [
                        'name' => $owner->display_name,
                        'relation' => 'Self',
                        'phone' => $owner->phone,
                        'email' => $owner->email,
                    ],
                    'is_signatory_different' => false,
                    'legal_terms' => [
                        'rent_amount' => $def['rent'],
                        'security_deposit' => $def['deposit'],
                        'fee_percentage' => 8.0,
                        'city_id' => $def['city_id'],
                        'city_name' => $def['city_name'],
                        'address' => $def['address_line_1'],
                        'financial_model_name' => 'Rent share',
                    ],
                    'bank_details' => [
                        'bank_name' => 'HDFC Bank',
                        'beneficiary_name' => $owner->display_name,
                        'account_number' => '50100' . rand(1000000, 9999999),
                        'ifsc_code' => 'HDFC0001234',
                        'account_type' => 'Savings',
                    ],
                    'verified_at' => now()->subMonths(2),
                    'verified_by' => $this->reviewerUser->id,
                    'prepared_by' => $this->executiveUser->id,
                    'generated_by' => $this->adminUser->id,
                ]);
                $mou->save();

                // Attach Sample Signed PDF if not already present
                if (!$mou->hasMedia('signed_pdf')) {
                    $mou->addMediaFromString($basePdfContent)
                        ->usingFileName("{$mou->number}-signed.pdf")
                        ->toMediaCollection('signed_pdf');
                }

                // 4. Create Onboarding Project
                $onboarding = OnboardingProject::firstOrNew(['property_id' => $property->id]);
                $onboarding->fill([
                    'status' => $def['onboarding_status'],
                    'assigned_executive_id' => $this->executiveUser->id,
                    'reviewer_id' => $this->reviewerUser->id,
                    'submitted_at' => in_array($def['onboarding_status'], ['Pending Review', 'Activated', 'Changes Requested']) ? now()->subDays(5) : null,
                    'reviewed_at' => $def['onboarding_status'] === 'Activated' ? now()->subDays(2) : ($def['onboarding_status'] === 'Changes Requested' ? now()->subDay() : null),
                    'review_notes' => $def['review_notes'] ?? null,
                ]);
                $onboarding->save();

                // 5. Create Financial Terms
                PropertyFinancialTerm::firstOrCreate(
                    ['property_id' => $property->id],
                    [
                        'mou_id' => $mou->id,
                        'pricing_model' => 'Rent share',
                        'fee_percentage' => 8.00,
                        'effective_from' => now()->subMonths(3)->toDateString(),
                        'created_by' => $this->adminUser->id,
                    ]
                );

                // 6. Create Pricing Version
                PropertyPricingVersion::firstOrCreate(
                    ['property_id' => $property->id, 'effective_from' => now()->subMonths(3)->toDateString()],
                    [
                        'rent' => $def['rent'],
                        'security_deposit' => $def['deposit'],
                        'society_fee' => $def['society_fee'],
                        'booking_amount' => round($def['rent'] * 0.25, -2),
                        'notes' => 'Standard annual market pricing.',
                        'created_by' => $this->adminUser->id,
                    ]
                );

                // 7. Configure Rooms based on BHK
                $this->seedRoomsForProperty($property, $def['bhk_name'], $def['sqft']);

                // 8. Configure Inventory (Keys is mandatory for all)
                $this->seedInventoryForProperty($property, $def['furnishing_name']);

                // 9. Configure Utilities
                $this->seedUtilitiesForProperty($property, $def['city_name']);

                // 10. Configure Amenities
                $this->seedAmenitiesForProperty($property, $def['property_type_name']);

                // 11. Map Nearby Establishments
                $this->seedEstablishmentsForProperty($property, $def['city_name']);

                // 12. Attach General Photo
                if ($property->photos()->count() === 0) {
                    PropertyPhoto::create([
                        'property_id' => $property->id,
                        'property_room_id' => null,
                        'file_path' => "properties/{$property->code}/elevation.jpg",
                        'title' => 'Exterior Building Elevation & Entrance',
                        'is_featured' => true,
                        'is_visible' => true,
                        'order_column' => 1,
                    ]);
                }

                // 13. Create Tenancy Agreement if Occupied
                if ($def['property_status'] === 'Occupied' && isset($def['tenant_index'])) {
                    $tenant = $tenants[$def['tenant_index'] % count($tenants)];
                    $pricingVersion = $property->pricingVersions()->latest()->first();

                    $agreementCode = sprintf('TNC-2026-%05d', $index + 2);
                    $agreement = TenancyAgreement::firstOrCreate(
                        ['property_id' => $property->id],
                        [
                            'branch_id' => $branchId,
                            'code' => $agreementCode,
                            'status' => 'active',
                            'start_date' => '2026-01-01',
                            'end_date' => '2026-12-31',
                            'rent_amount' => $def['rent'],
                            'security_deposit' => $def['deposit'],
                            'lock_in_period_months' => 6,
                            'notice_period_days' => 30,
                            'pricing_version_id' => $pricingVersion?->id,
                            'keys_handed_over' => true,
                            'keys_handed_over_at' => '2026-01-01 10:00:00',
                            'key_handover_notes' => 'Set of 3 master keys handed over to primary tenant.',
                            'signed_at' => '2025-12-28 14:00:00',
                            'signed_by_tenant' => true,
                        ]
                    );

                    TenancyRole::firstOrCreate(
                        [
                            'tenancy_agreement_id' => $agreement->id,
                            'party_id' => $tenant->id,
                        ],
                        [
                            'role_type' => 'Primary Tenant',
                            'is_primary' => true,
                        ]
                    );
                }
            });

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('Successfully seeded 50 properties, complete with Opportunities, MOUs, Onboarding Projects, and Tenancies!');
    }

    protected function setupUsers(): void
    {
        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@dwelly.in'],
            ['name' => 'Dwelly Admin', 'password' => bcrypt('password')]
        );

        $this->executiveUser = User::firstOrCreate(
            ['email' => 'rahul.operations@dwelly.in'],
            ['name' => 'Rahul Goswami', 'password' => bcrypt('password')]
        );

        $this->reviewerUser = User::firstOrCreate(
            ['email' => 'ananya.reviewer@dwelly.in'],
            ['name' => 'Ananya Sharma', 'password' => bcrypt('password')]
        );

        if (method_exists($this->adminUser, 'assignRole')) {
            $this->adminUser->assignRole('Business Owner');
            $this->executiveUser->assignRole('Operations Executive');
            $this->reviewerUser->assignRole('Operations Manager');
        }

        $ghyBranch = Branch::where('code', 'GHY')->orWhere('city', 'Guwahati')->first();
        $blrBranch = Branch::where('code', 'BLR')->orWhere('city', 'Bangalore')->first();

        if ($ghyBranch) {
            $this->executiveUser->branches()->syncWithoutDetaching([$ghyBranch->id]);
            $this->reviewerUser->branches()->syncWithoutDetaching([$ghyBranch->id]);
        }
        if ($blrBranch) {
            $this->reviewerUser->branches()->syncWithoutDetaching([$blrBranch->id]);
        }
    }

    protected function setupGeographicData(): void
    {
        $this->cities['Guwahati'] = City::where('name', 'Guwahati')->first();
        $this->cities['Bangalore'] = City::where('name', 'Bangalore')->first();

        // Ensure Bangalore has localities
        if ($this->cities['Bangalore']) {
            $bangaloreLocalities = [
                ['name' => 'Koramangala', 'pincode' => '560034'],
                ['name' => 'Indiranagar', 'pincode' => '560038'],
                ['name' => 'HSR Layout', 'pincode' => '560102'],
                ['name' => 'Whitefield', 'pincode' => '560066'],
                ['name' => 'Bellandur', 'pincode' => '560103'],
                ['name' => 'Electronic City', 'pincode' => '560100'],
                ['name' => 'Jayanagar', 'pincode' => '560041'],
            ];

            foreach ($bangaloreLocalities as $loc) {
                Locality::firstOrCreate(
                    ['city_id' => $this->cities['Bangalore']->id, 'name' => $loc['name']],
                    ['slug' => Str::slug($loc['name']), 'pincode' => $loc['pincode'], 'is_active' => true]
                );
            }
        }

        // Cache localities by city and name
        $this->localities['Guwahati'] = Locality::where('city_id', $this->cities['Guwahati']?->id)->pluck('id', 'name')->toArray();
        $this->localities['Bangalore'] = Locality::where('city_id', $this->cities['Bangalore']?->id)->pluck('id', 'name')->toArray();

        $this->branches['Guwahati'] = Branch::where('code', 'GHY')->orWhere('city', 'Guwahati')->value('id') ?? 1;
        $this->branches['Bangalore'] = Branch::where('code', 'BLR')->orWhere('city', 'Bangalore')->value('id') ?? 2;
    }

    protected function loadReferenceData(): void
    {
        $this->propertyTypes = PropertyType::pluck('id', 'name')->toArray();
        $this->bhkTypes = DB::table('bhk_types')->pluck('id', 'name')->toArray();
        $this->furnishingTypes = FurnishingType::pluck('id', 'name')->toArray();
        $this->flooringTypes = DB::table('flooring_types')->pluck('id', 'name')->toArray();
        $this->financialModels = FinancialModel::pluck('id', 'name')->toArray();
        $this->opportunitySources = OpportunitySource::pluck('id')->toArray();
        $this->utilityTypes = UtilityType::pluck('id', 'slug')->toArray();
        $this->inventoryTypes = InventoryType::pluck('id', 'slug')->toArray();
        $this->amenityTypes = AmenityType::pluck('id', 'name')->toArray();
        $this->roomDefinitions = RoomDefinition::pluck('id', 'name')->toArray();
        $this->vendorTrades = VendorTrade::pluck('id', 'slug')->toArray();

        $this->establishments['Guwahati'] = Establishment::where('city', 'like', '%Guwahati%')
            ->orWhereIn('name', ['Guwahati Railway Station', 'Cotton Collegiate School', 'Nehru Park', 'Gauhati Medical College & Hospital'])
            ->pluck('id')->toArray();

        $this->establishments['Bangalore'] = Establishment::where('city', 'like', '%Bangalore%')
            ->orWhereIn('name', ['Indiranagar Metro Station', 'Manyata Tech Park', 'Phoenix Marketcity', 'Manipal Hospital'])
            ->pluck('id')->toArray();
    }

    protected function seedOwners(): array
    {
        $ownerData = [
            ['name' => 'Dr. Himanta Barua', 'phone' => '9864012345', 'email' => 'himanta.barua@gmail.com', 'city' => 'Guwahati', 'pan' => 'ABCPB1234M'],
            ['name' => 'Biren Saikia', 'phone' => '9435012345', 'email' => 'biren.saikia@yahoo.com', 'city' => 'Guwahati', 'pan' => 'BCDPS2345N'],
            ['name' => 'Dipankar Goswami', 'phone' => '9854012345', 'email' => 'dipankar.goswami@outlook.com', 'city' => 'Guwahati', 'pan' => 'CDEPG3456P'],
            ['name' => 'Monideepa Bezbaruah', 'phone' => '9706012345', 'email' => 'monideepa.b@gmail.com', 'city' => 'Guwahati', 'pan' => 'DEFPM4567Q'],
            ['name' => 'Partha Pratim Sarma', 'phone' => '9864112345', 'email' => 'partha.sarma@gmail.com', 'city' => 'Guwahati', 'pan' => 'EFGPS5678R'],
            ['name' => 'Nabajit Deka', 'phone' => '9435112345', 'email' => 'nabajit.deka@rediffmail.com', 'city' => 'Guwahati', 'pan' => 'FGKPD6789S'],
            ['name' => 'Anupam Bordoloi', 'phone' => '9854112345', 'email' => 'anupam.bordoloi@gmail.com', 'city' => 'Guwahati', 'pan' => 'GHLPB7890T'],
            ['name' => 'Gitashree Kalita', 'phone' => '9706112345', 'email' => 'gitashree.kalita@yahoo.co.in', 'city' => 'Guwahati', 'pan' => 'HJKPK8901U'],
            ['name' => 'Bhaskar Medhi', 'phone' => '9864212345', 'email' => 'bhaskar.medhi@hotmail.com', 'city' => 'Guwahati', 'pan' => 'JKLPM9012V'],
            ['name' => 'Joyeeta Bhattacharjee', 'phone' => '9435212345', 'email' => 'joyeeta.bhatt@gmail.com', 'city' => 'Guwahati', 'pan' => 'KLMNB0123W'],
            ['name' => 'Sanjib Chetia', 'phone' => '9854212345', 'email' => 'sanjib.chetia@gmail.com', 'city' => 'Guwahati', 'pan' => 'LMNOP1234X'],
            ['name' => 'Dhrubajyoti Hazarika', 'phone' => '9706212345', 'email' => 'dhruba.hazarika@gmail.com', 'city' => 'Guwahati', 'pan' => 'MNOPQ2345Y'],
            ['name' => 'Mridul Talukdar', 'phone' => '9864312345', 'email' => 'mridul.talukdar@gmail.com', 'city' => 'Guwahati', 'pan' => 'NOPQR3456Z'],
            ['name' => 'Pranab Phukan', 'phone' => '9435312345', 'email' => 'pranab.phukan@yahoo.com', 'city' => 'Guwahati', 'pan' => 'OPQRS4567A'],
            ['name' => 'Ranjan Kakati', 'phone' => '9854312345', 'email' => 'ranjan.kakati@gmail.com', 'city' => 'Guwahati', 'pan' => 'PQRST5678B'],
            ['name' => 'Siddhartha Dutta', 'phone' => '9706312345', 'email' => 'siddhartha.dutta@gmail.com', 'city' => 'Guwahati', 'pan' => 'QRSTU6789C'],
            ['name' => 'Utpal Bhagawati', 'phone' => '9864412345', 'email' => 'utpal.bhagawati@rediffmail.com', 'city' => 'Guwahati', 'pan' => 'RSTUV7890D'],
            ['name' => 'Arup Kumar Das', 'phone' => '9435412345', 'email' => 'arup.k.das@gmail.com', 'city' => 'Guwahati', 'pan' => 'STUVW8901E'],
            ['name' => 'Rajesh & Sunita Agarwal', 'phone' => '9880112345', 'email' => 'agarwal.homes@gmail.com', 'city' => 'Bangalore', 'pan' => 'TUVWA9012F'],
            ['name' => 'Brigadier S. K. Sharma (Retd)', 'phone' => '9845012345', 'email' => 'sksharma.brig@gmail.com', 'city' => 'Bangalore', 'pan' => 'UVWXB0123G'],
            ['name' => 'Dr. Arvind Swaminathan', 'phone' => '9900112345', 'email' => 'arvind.swami@apollo.org', 'city' => 'Bangalore', 'pan' => 'VWXYC1234H'],
            ['name' => 'Kavitha Reddy', 'phone' => '9845112345', 'email' => 'kavitha.reddy@techventures.in', 'city' => 'Bangalore', 'pan' => 'WXYZD2345I'],
            ['name' => 'Vikram Malhotra', 'phone' => '9900212345', 'email' => 'vikram.malhotra@inventure.com', 'city' => 'Bangalore', 'pan' => 'XYZAE3456J'],
            ['name' => 'Suresh Venkatraman', 'phone' => '9880212345', 'email' => 'suresh.venkat@infosys.com', 'city' => 'Bangalore', 'pan' => 'YZAFF4567K'],
            ['name' => 'Sneha Kulkarni', 'phone' => '9845212345', 'email' => 'sneha.kulkarni@wipro.com', 'city' => 'Bangalore', 'pan' => 'ZABGG5678L'],
            ['name' => 'Aditya Vardhan', 'phone' => '9900312345', 'email' => 'aditya.vardhan@gmail.com', 'city' => 'Bangalore', 'pan' => 'ABCHH6789M'],
            ['name' => 'Meera Nambiar', 'phone' => '9880312345', 'email' => 'meera.nambiar@yahoo.com', 'city' => 'Bangalore', 'pan' => 'BCDII7890N'],
            ['name' => 'Ramesh Nair', 'phone' => '9845312345', 'email' => 'ramesh.nair@accenture.com', 'city' => 'Bangalore', 'pan' => 'CDEJJ8901P'],
        ];

        $owners = [];
        foreach ($ownerData as $data) {
            $party = Party::firstOrCreate(
                ['email' => $data['email']],
                [
                    'party_type' => 'individual',
                    'display_name' => $data['name'],
                    'phone' => $data['phone'],
                ]
            );

            if (!$party->ownerProfile()->exists()) {
                $party->ownerProfile()->create([]);
            }

            if (!$party->individual()->exists()) {
                $party->individual()->create([
                    'name' => $data['name'],
                    'pan_number' => $data['pan'],
                    'aadhaar_number' => '98' . rand(1000000000, 9999999999),
                ]);
            }

            if (!$party->bankAccounts()->exists()) {
                $party->bankAccounts()->create([
                    'bank_name' => 'HDFC Bank',
                    'beneficiary_name' => $data['name'],
                    'account_number' => '50100' . rand(1000000, 9999999),
                    'ifsc_code' => 'HDFC0001234',
                    'is_primary' => true,
                    'is_verified' => true,
                ]);
            }

            $owners[] = $party;
        }

        return $owners;
    }

    protected function seedTenants(): array
    {
        return (new TenantSeeder())->seedTenants();
    }

    protected function seedVendors(): array
    {
        $vendorData = [
            // Guwahati Vendors
            [
                'name' => 'Brahmaputra Electrical & Engineering',
                'trade_slug' => 'electrical',
                'party_type' => 'organization',
                'legal_name' => 'Brahmaputra Electrical Works Pvt Ltd',
                'contact_person' => 'Nabajyoti Nath',
                'phone' => '9864201001',
                'email' => 'service@brahmaputraelectricals.com',
                'gstin' => '18AABCB1234F1Z1',
                'pan' => 'AABCB1234F',
                'address' => 'G.S. Road, Christian Basti',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781005',
                'regions' => ['Guwahati', 'Christian Basti', 'Dispur', 'Beltola', 'Ganeshguri'],
                'rating' => 4.90,
                'jobs' => 142,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'A-Grade electrical contractor license verified. Preferred vendor for all Guwahati properties.',
                'bank' => ['name' => 'State Bank of India', 'acc' => '30198765432', 'ifsc' => 'SBIN0000078'],
            ],
            [
                'name' => 'Kamakhya Plumbing & Sanitation Solutions',
                'trade_slug' => 'plumbing',
                'party_type' => 'organization',
                'legal_name' => 'Kamakhya Plumbing & Sanitary Services',
                'contact_person' => 'Pranjal Sarma',
                'phone' => '9864201002',
                'email' => 'kamakhyaplumbing@gmail.com',
                'gstin' => '18AAECK2345G1Z2',
                'pan' => 'AAECK2345G',
                'address' => 'Ulubari Flyover Point, B.K. Kakati Road',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781007',
                'regions' => ['Guwahati', 'Ulubari', 'Bhangagarh', 'Paltan Bazaar'],
                'rating' => 4.80,
                'jobs' => 98,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Master plumber license verified. Rapid response team for pipeline leakages.',
                'bank' => ['name' => 'HDFC Bank', 'acc' => '50200987654321', 'ifsc' => 'HDFC0000399'],
            ],
            [
                'name' => 'Pranab Jyoti Deka (Woodcraft & Carpentry)',
                'trade_slug' => 'carpentry',
                'party_type' => 'individual',
                'contact_person' => 'Pranab Jyoti Deka',
                'phone' => '9854201003',
                'email' => 'pranab.carpenter.ghy@gmail.com',
                'pan' => 'CJKPD3456H',
                'aadhaar' => '981234567890',
                'address' => 'Zoo Narengi Road, Geetanagar',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781024',
                'regions' => ['Guwahati', 'Zoo Road', 'Dispur', 'Hatigaon'],
                'rating' => 4.75,
                'jobs' => 64,
                'preferred' => false,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Specialist in modular kitchen repair, doors, hinges, and lock replacements.',
                'bank' => ['name' => 'Punjab National Bank', 'acc' => '1029000100123456', 'ifsc' => 'PUNB0010200'],
            ],
            [
                'name' => 'Assam Cool Air HVAC Services',
                'trade_slug' => 'hvac-air-conditioning',
                'party_type' => 'organization',
                'legal_name' => 'Assam Air Cool Systems LLP',
                'contact_person' => 'Bikash Choudhury',
                'phone' => '9864201004',
                'email' => 'support@assamcoolair.in',
                'gstin' => '18AAGCA4567H1Z3',
                'pan' => 'AAGCA4567H',
                'address' => 'Six Mile, VIP Road Junction',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781022',
                'regions' => ['Guwahati', 'Six Mile', 'Khanapara', 'Beltola'],
                'rating' => 4.85,
                'jobs' => 110,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Authorized service provider for Daikin, Voltas, and Blue Star air conditioners.',
                'bank' => ['name' => 'ICICI Bank', 'acc' => '005505012345', 'ifsc' => 'ICIC0000055'],
            ],
            [
                'name' => 'Rainbow Colors Painting & Polishing',
                'trade_slug' => 'painting',
                'party_type' => 'organization',
                'legal_name' => 'Rainbow Surface Finishers',
                'contact_person' => 'Deben Kalita',
                'phone' => '9706201005',
                'email' => 'rainbowcolors.ghy@gmail.com',
                'gstin' => '18AAPCR5678J1Z4',
                'pan' => 'AAPCR5678J',
                'address' => 'Hatigaon Main Road',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781038',
                'regions' => ['Guwahati', 'Hatigaon', 'Beltola', 'Dispur', 'Ulubari'],
                'rating' => 4.65,
                'jobs' => 52,
                'preferred' => false,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Complete apartment turnover repainting, waterproofing, and texture finish.',
                'bank' => ['name' => 'Axis Bank', 'acc' => '918020054321098', 'ifsc' => 'UTIB0000188'],
            ],
            [
                'name' => 'Guwahati CleanPro Deep Cleaning',
                'trade_slug' => 'cleaning-sanitization',
                'party_type' => 'organization',
                'legal_name' => 'Guwahati CleanPro Hygiene Solutions',
                'contact_person' => 'Jahnabi Barman',
                'phone' => '9864201006',
                'email' => 'contact@guwahaticleanpro.com',
                'gstin' => '18AAECG6789K1Z5',
                'pan' => 'AAECG6789K',
                'address' => 'Ganeshguri Chariali, Commercial Complex',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781006',
                'regions' => ['Guwahati', 'Christian Basti', 'Ganeshguri', 'Six Mile', 'Dispur'],
                'rating' => 4.92,
                'jobs' => 185,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Full home deep cleaning, move-in/move-out turnover, sofa & mattress shampooing.',
                'bank' => ['name' => 'HDFC Bank', 'acc' => '50200876543210', 'ifsc' => 'HDFC0000085'],
            ],
            [
                'name' => 'QuickFix Appliances & Electronics',
                'trade_slug' => 'appliance-repair',
                'party_type' => 'individual',
                'contact_person' => 'Manoranjan Roy',
                'phone' => '9854201007',
                'email' => 'quickfix.roy@gmail.com',
                'pan' => 'DMKPR7890L',
                'aadhaar' => '982345678901',
                'address' => 'Rajgarh Road, Bhangagarh',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781003',
                'regions' => ['Guwahati', 'Bhangagarh', 'Ulubari', 'Zoo Road'],
                'rating' => 4.70,
                'jobs' => 79,
                'preferred' => false,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Certified repair technician for refrigerators, washing machines, microwaves, and geysers.',
                'bank' => ['name' => 'State Bank of India', 'acc' => '20345678901', 'ifsc' => 'SBIN0000078'],
            ],
            [
                'name' => 'Barman Civil Works & Masonry',
                'trade_slug' => 'general-civil-maintenance',
                'party_type' => 'individual',
                'contact_person' => 'Gajen Barman',
                'phone' => '9435201008',
                'email' => 'gajen.civil@rediffmail.com',
                'pan' => 'ENKPB8901M',
                'aadhaar' => '983456789012',
                'address' => 'Khanapara Farm Gate, G.S. Road',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781022',
                'regions' => ['Guwahati', 'Khanapara', 'Six Mile', 'Beltola'],
                'rating' => 4.60,
                'jobs' => 43,
                'preferred' => false,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Tile re-grouting, seepage repair, civil touch-ups, and plaster repairs.',
                'bank' => ['name' => 'UCO Bank', 'acc' => '04560110098765', 'ifsc' => 'UCBA0000456'],
            ],
            [
                'name' => 'NorthEast Facility Solutions',
                'trade_slug' => 'general-civil-maintenance',
                'party_type' => 'organization',
                'legal_name' => 'NorthEast Facility Management Pvt Ltd',
                'contact_person' => 'Hemanta Kalita',
                'phone' => '9864201009',
                'email' => 'info@nefacility.in',
                'gstin' => '18AAACN9012N1Z6',
                'pan' => 'AAACN9012N',
                'address' => 'Basistha Chariali, Beltola',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'pincode' => '781028',
                'regions' => ['Guwahati', 'Beltola', 'Hatigaon', 'Khanapara'],
                'rating' => 4.50,
                'jobs' => 12,
                'preferred' => false,
                'status' => VendorOnboardingStatus::PENDING_VERIFICATION,
                'notes' => 'Submitted documents for onboarding; pending trade verification.',
                'bank' => ['name' => 'Federal Bank', 'acc' => '12340200005678', 'ifsc' => 'FDRL0001234'],
            ],

            // Bangalore Vendors
            [
                'name' => 'Apex Electricals & Smart Home Solutions',
                'trade_slug' => 'electrical',
                'party_type' => 'organization',
                'legal_name' => 'Apex Electrical Innovations Pvt Ltd',
                'contact_person' => 'Sunil Murthy',
                'phone' => '9880202001',
                'email' => 'contact@apexelectricals.co.in',
                'gstin' => '29AABCA9876P1Z1',
                'pan' => 'AABCA9876P',
                'address' => '27th Main Road, Sector 1, HSR Layout',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560102',
                'regions' => ['Bangalore', 'HSR Layout', 'Koramangala', 'Bellandur'],
                'rating' => 4.94,
                'jobs' => 210,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Class-1 Electrical Contractor, BESCOM certified, smart home and inverter specialist.',
                'bank' => ['name' => 'HDFC Bank', 'acc' => '50200123456789', 'ifsc' => 'HDFC0001234'],
            ],
            [
                'name' => 'Kaveri Plumbing & Water Management',
                'trade_slug' => 'plumbing',
                'party_type' => 'organization',
                'legal_name' => 'Kaveri Hydro Solutions LLP',
                'contact_person' => 'Manjunath Gowda',
                'phone' => '9845202002',
                'email' => 'service@kaveriplumbing.com',
                'gstin' => '29AAECK8765Q1Z2',
                'pan' => 'AAECK8765Q',
                'address' => 'ITPL Main Road, Whitefield',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560066',
                'regions' => ['Bangalore', 'Whitefield', 'Bellandur', 'Electronic City'],
                'rating' => 4.88,
                'jobs' => 165,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'BWSSB certified plumbing and sewage pipeline maintenance team.',
                'bank' => ['name' => 'ICICI Bank', 'acc' => '003905098765', 'ifsc' => 'ICIC0000039'],
            ],
            [
                'name' => 'Bengaluru CoolTech HVAC & Refrigeration',
                'trade_slug' => 'hvac-air-conditioning',
                'party_type' => 'organization',
                'legal_name' => 'Bengaluru CoolTech Services Pvt Ltd',
                'contact_person' => 'Vinay Venkatesh',
                'phone' => '9900202003',
                'email' => 'support@blr-cooltech.in',
                'gstin' => '29AAGCB7654R1Z3',
                'pan' => 'AAGCB7654R',
                'address' => '100 Feet Road, Indiranagar',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560038',
                'regions' => ['Bangalore', 'Indiranagar', 'Koramangala', 'Whitefield'],
                'rating' => 4.91,
                'jobs' => 140,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Multi-brand AC installation, gas charging, coil cleaning, and VRV/VRF servicing.',
                'bank' => ['name' => 'Axis Bank', 'acc' => '919020012345678', 'ifsc' => 'UTIB0000199'],
            ],
            [
                'name' => 'Venkateshwara Woodcraft & Interiors',
                'trade_slug' => 'carpentry',
                'party_type' => 'organization',
                'legal_name' => 'Sri Venkateshwara Carpentry Works',
                'contact_person' => 'K. Narayana Swamy',
                'phone' => '9880202004',
                'email' => 'svwoodcraft.blr@gmail.com',
                'gstin' => '29AAPCV6543S1Z4',
                'pan' => 'AAPCV6543S',
                'address' => '9th Main Road, 4th Block, Jayanagar',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560041',
                'regions' => ['Bangalore', 'Jayanagar', 'Koramangala', 'HSR Layout'],
                'rating' => 4.82,
                'jobs' => 88,
                'preferred' => false,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Custom furniture adjustments, hinge repairs, sliding wardrobe rollers, and lock fitting.',
                'bank' => ['name' => 'Canara Bank', 'acc' => '0423101009876', 'ifsc' => 'CNRB0000423'],
            ],
            [
                'name' => 'Asian Paints ProCare Contractors (Bangalore South)',
                'trade_slug' => 'painting',
                'party_type' => 'organization',
                'legal_name' => 'ProCare Surfaces & Coating LLP',
                'contact_person' => 'Anand Krishnan',
                'phone' => '9845202005',
                'email' => 'south.procare@gmail.com',
                'gstin' => '29AAACP5432T1Z5',
                'pan' => 'AAACP5432T',
                'address' => '14th Main, HSR Layout Sector 4',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560102',
                'regions' => ['Bangalore', 'HSR Layout', 'Koramangala', 'Bellandur', 'Jayanagar'],
                'rating' => 4.86,
                'jobs' => 125,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Standardized wall putty, primer, and emulsion painting with dust-free sanding.',
                'bank' => ['name' => 'Kotak Mahindra Bank', 'acc' => '7811223344', 'ifsc' => 'KKBK0000421'],
            ],
            [
                'name' => 'UrbanSparkle Facility & Deep Clean Services',
                'trade_slug' => 'cleaning-sanitization',
                'party_type' => 'organization',
                'legal_name' => 'UrbanSparkle Hospitality Services Pvt Ltd',
                'contact_person' => 'Deepa Shenoy',
                'phone' => '9900202006',
                'email' => 'operations@urbansparkle.in',
                'gstin' => '29AAECU4321U1Z6',
                'pan' => 'AAECU4321U',
                'address' => '80 Feet Road, 4th Block, Koramangala',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560034',
                'regions' => ['Bangalore', 'Koramangala', 'Indiranagar', 'HSR Layout', 'Whitefield'],
                'rating' => 4.95,
                'jobs' => 230,
                'preferred' => true,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Premium turnover deep cleaning, kitchen degreasing, bathroom descaling, balcony wash.',
                'bank' => ['name' => 'HDFC Bank', 'acc' => '50200034567890', 'ifsc' => 'HDFC0000053'],
            ],
            [
                'name' => 'Suresh Kumar Appliance Care',
                'trade_slug' => 'appliance-repair',
                'party_type' => 'individual',
                'contact_person' => 'Suresh Kumar V.',
                'phone' => '9880202007',
                'email' => 'suresh.appliances.blr@gmail.com',
                'pan' => 'BVKPS3210V',
                'aadhaar' => '984567890123',
                'address' => 'Outer Ring Road, Bellandur',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560103',
                'regions' => ['Bangalore', 'Bellandur', 'HSR Layout', 'Whitefield'],
                'rating' => 4.78,
                'jobs' => 95,
                'preferred' => false,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Chimney, cooktop hob, RO water purifier, and water heater servicing technician.',
                'bank' => ['name' => 'State Bank of India', 'acc' => '30876543210', 'ifsc' => 'SBIN0001811'],
            ],
            [
                'name' => 'SouthCity Civil Infrastructure & Repair Works',
                'trade_slug' => 'general-civil-maintenance',
                'party_type' => 'organization',
                'legal_name' => 'SouthCity Civil Contractors LLP',
                'contact_person' => 'Praveen Hegde',
                'phone' => '9845202008',
                'email' => 'southcitycivil@gmail.com',
                'gstin' => '29AAICS2109W1Z7',
                'pan' => 'AAICS2109W',
                'address' => 'Electronic City Phase 1',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560100',
                'regions' => ['Bangalore', 'Electronic City', 'HSR Layout', 'Bellandur'],
                'rating' => 4.72,
                'jobs' => 60,
                'preferred' => false,
                'status' => VendorOnboardingStatus::VERIFIED,
                'notes' => 'Waterproofing terrace, grouting, civil modifications, and masonry repairs.',
                'bank' => ['name' => 'Bank of Baroda', 'acc' => '19870200004321', 'ifsc' => 'BARB0VJECIT'],
            ],
            [
                'name' => 'GreenGlaze Cleaning Co.',
                'trade_slug' => 'cleaning-sanitization',
                'party_type' => 'organization',
                'legal_name' => 'GreenGlaze Hygiene Pvt Ltd',
                'contact_person' => 'Rhea Deshmukh',
                'phone' => '9900202009',
                'email' => 'info@greenglaze.co',
                'gstin' => '29AAACG1098X1Z8',
                'pan' => 'AAACG1098X',
                'address' => 'ECC Road, Whitefield',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560066',
                'regions' => ['Bangalore', 'Whitefield'],
                'rating' => 4.40,
                'jobs' => 8,
                'preferred' => false,
                'status' => VendorOnboardingStatus::PENDING_VERIFICATION,
                'notes' => 'Submitted business registration certificate; KYC in verification queue.',
                'bank' => ['name' => 'HDFC Bank', 'acc' => '50200056789012', 'ifsc' => 'HDFC0001051'],
            ],
        ];

        $vendors = [];
        $provisioningService = app(AccountingProvisioningService::class);

        foreach ($vendorData as $data) {
            $tradeId = $this->vendorTrades[$data['trade_slug']] ?? null;
            if (!$tradeId) {
                $trade = VendorTrade::where('slug', $data['trade_slug'])->first();
                $tradeId = $trade?->id;
            }

            $party = Party::firstOrCreate(
                ['email' => $data['email']],
                [
                    'party_type' => $data['party_type'],
                    'display_name' => $data['name'],
                    'phone' => $data['phone'],
                    'is_tax_registered' => !empty($data['gstin']),
                    'gst_registration_type' => !empty($data['gstin']) ? 'regular' : null,
                    'state_id' => $data['state'] === 'Assam' ? 18 : 29,
                ]
            );

            if ($data['party_type'] === 'individual' && !$party->individual()->exists()) {
                $party->individual()->create([
                    'name' => $data['contact_person'],
                    'pan_number' => $data['pan'] ?? null,
                    'aadhaar_number' => $data['aadhaar'] ?? null,
                ]);
            } elseif ($data['party_type'] === 'organization' && !$party->organization()->exists()) {
                $party->organization()->create([
                    'legal_name' => $data['legal_name'] ?? $data['name'],
                    'pan' => $data['pan'] ?? null,
                    'gstin' => $data['gstin'] ?? null,
                    'contact_person_name' => $data['contact_person'],
                    'contact_person_phone' => $data['phone'],
                ]);
            }

            if (!$party->addresses()->exists()) {
                $party->addresses()->create([
                    'type' => $data['party_type'] === 'organization' ? 'registered_office' : 'residential',
                    'address_line_1' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'pincode' => $data['pincode'],
                    'country' => 'India',
                    'is_primary' => true,
                ]);
            }

            if (!$party->bankAccounts()->exists()) {
                $party->bankAccounts()->create([
                    'bank_name' => $data['bank']['name'],
                    'beneficiary_name' => $data['party_type'] === 'organization' ? ($data['legal_name'] ?? $data['name']) : $data['contact_person'],
                    'account_number' => $data['bank']['acc'],
                    'ifsc_code' => $data['bank']['ifsc'],
                    'is_primary' => true,
                    'is_verified' => true,
                ]);
            }

            if ($tradeId && !$party->vendorProfile()->exists()) {
                $party->vendorProfile()->create([
                    'vendor_trade_id' => $tradeId,
                    'gstin' => $data['gstin'] ?? null,
                    'service_regions' => $data['regions'],
                    'rating' => $data['rating'],
                    'total_jobs_completed' => $data['jobs'],
                    'is_preferred' => $data['preferred'],
                    'onboarding_status' => $data['status'],
                    'verification_notes' => $data['notes'],
                    'verified_at' => $data['status'] === VendorOnboardingStatus::VERIFIED ? now()->subMonths(rand(2, 6)) : null,
                    'verified_by_id' => $data['status'] === VendorOnboardingStatus::VERIFIED ? $this->reviewerUser?->id : null,
                ]);
            }

            // Sync accounting readiness
            try {
                $provisioningService->ensurePartyAccountingReady($party);
            } catch (\Throwable $e) {
                // Ignore accounting provisioning issues in environments where CoA might not be loaded yet
            }

            $vendors[] = $party;
        }

        return $vendors;
    }

    protected function getPropertyDefinitions(): array
    {
        $gauLocs = $this->localities['Guwahati'];
        $blrLocs = $this->localities['Bangalore'];

        $definitions = [];

        // --- 35 GUWAHATI PROPERTIES ---
        $gauConfigs = [
            // 15 Occupied (0 to 14)
            ['code' => 'GAU-0002', 'building' => 'Subham Regency', 'loc' => 'Christian Basti', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1650, 'rent' => 32000, 'deposit' => 96000, 'society' => 3500, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1584, 'lng' => 91.7765, 'floor' => 4, 'total_floors' => 9, 'owner_idx' => 0, 'tenant_idx' => 0],
            ['code' => 'GAU-0003', 'building' => 'Protech View', 'loc' => 'Beltola', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1150, 'rent' => 22000, 'deposit' => 66000, 'society' => 2500, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1311, 'lng' => 91.7924, 'floor' => 2, 'total_floors' => 6, 'owner_idx' => 1, 'tenant_idx' => 1],
            ['code' => 'GAU-0004', 'building' => 'Donyi Polo Apartments', 'loc' => 'Zoo Road', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1500, 'rent' => 28000, 'deposit' => 84000, 'society' => 3000, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1702, 'lng' => 91.7812, 'floor' => 5, 'total_floors' => 8, 'owner_idx' => 2, 'tenant_idx' => 2],
            ['code' => 'GAU-0005', 'building' => 'Brahmaputra Enclave', 'loc' => 'Ulubari', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1050, 'rent' => 20000, 'deposit' => 60000, 'society' => 2200, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1725, 'lng' => 91.7588, 'floor' => 3, 'total_floors' => 7, 'owner_idx' => 3, 'tenant_idx' => 3],
            ['code' => 'GAU-0006', 'building' => 'Greenwood Residency', 'loc' => 'Dispur', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1700, 'rent' => 35000, 'deposit' => 105000, 'society' => 3800, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1415, 'lng' => 91.7891, 'floor' => 6, 'total_floors' => 10, 'owner_idx' => 4, 'tenant_idx' => 4],
            ['code' => 'GAU-0007', 'building' => 'Royal Heritage Villa', 'loc' => 'Khanapara', 'bhk' => '4 BHK', 'type' => 'Villa', 'furnish' => 'Fully Furnished', 'sqft' => 2800, 'rent' => 55000, 'deposit' => 165000, 'society' => 5000, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1154, 'lng' => 91.8211, 'floor' => 1, 'total_floors' => 2, 'owner_idx' => 5, 'tenant_idx' => 5],
            ['code' => 'GAU-0008', 'building' => 'Silver Arc', 'loc' => 'Ganeshguri', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1200, 'rent' => 24000, 'deposit' => 72000, 'society' => 2800, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1511, 'lng' => 91.7825, 'floor' => 1, 'total_floors' => 5, 'owner_idx' => 6, 'tenant_idx' => 6],
            ['code' => 'GAU-0009', 'building' => 'Mayur Gardens', 'loc' => 'Six Mile', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Unfurnished', 'sqft' => 1400, 'rent' => 21000, 'deposit' => 63000, 'society' => 2600, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1365, 'lng' => 91.8105, 'floor' => 4, 'total_floors' => 8, 'owner_idx' => 7, 'tenant_idx' => 7],
            ['code' => 'GAU-0010', 'building' => 'Nilachal Heights', 'loc' => 'Hatigaon', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1100, 'rent' => 23000, 'deposit' => 69000, 'society' => 2400, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1350, 'lng' => 91.7810, 'floor' => 3, 'total_floors' => 6, 'owner_idx' => 8, 'tenant_idx' => 8],
            ['code' => 'GAU-0011', 'building' => 'Uttarayan Greens', 'loc' => 'Bhangagarh', 'bhk' => '1 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 650, 'rent' => 16000, 'deposit' => 48000, 'society' => 1800, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1620, 'lng' => 91.7650, 'floor' => 2, 'total_floors' => 7, 'owner_idx' => 9, 'tenant_idx' => 9],
            ['code' => 'GAU-0012', 'building' => 'Exotica Greens', 'loc' => 'Christian Basti', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1600, 'rent' => 34000, 'deposit' => 102000, 'society' => 3500, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1590, 'lng' => 91.7770, 'floor' => 7, 'total_floors' => 11, 'owner_idx' => 10, 'tenant_idx' => 10],
            ['code' => 'GAU-0013', 'building' => 'Shine Heaven Studio', 'loc' => 'Beltola', 'bhk' => '1 RK', 'type' => 'Studio', 'furnish' => 'Fully Furnished', 'sqft' => 450, 'rent' => 13500, 'deposit' => 40500, 'society' => 1500, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1320, 'lng' => 91.7930, 'floor' => 1, 'total_floors' => 4, 'owner_idx' => 11, 'tenant_idx' => 11],
            ['code' => 'GAU-0014', 'building' => 'Paramount Heights', 'loc' => 'Dispur', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1180, 'rent' => 22500, 'deposit' => 67500, 'society' => 2500, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1420, 'lng' => 91.7895, 'floor' => 5, 'total_floors' => 8, 'owner_idx' => 12, 'tenant_idx' => 12],
            ['code' => 'GAU-0015', 'building' => 'Ambience Landmark', 'loc' => 'Six Mile', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1550, 'rent' => 30000, 'deposit' => 90000, 'society' => 3200, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1370, 'lng' => 91.8110, 'floor' => 2, 'total_floors' => 9, 'owner_idx' => 13, 'tenant_idx' => 13],
            ['code' => 'GAU-0016', 'building' => 'Nilgiri Villa Estate', 'loc' => 'Khanapara', 'bhk' => '4 BHK', 'type' => 'Villa', 'furnish' => 'Semi-Furnished', 'sqft' => 2600, 'rent' => 48000, 'deposit' => 144000, 'society' => 4500, 'status' => 'Occupied', 'onboard' => 'Activated', 'lat' => 26.1160, 'lng' => 91.8220, 'floor' => 1, 'total_floors' => 2, 'owner_idx' => 14, 'tenant_idx' => 14],

            // 11 Vacant (15 to 25)
            ['code' => 'GAU-0017', 'building' => 'Oasis Residency', 'loc' => 'Ganeshguri', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1120, 'rent' => 21000, 'deposit' => 63000, 'society' => 2400, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1520, 'lng' => 91.7830, 'floor' => 3, 'total_floors' => 6, 'owner_idx' => 15],
            ['code' => 'GAU-0018', 'building' => 'Kamakhya View Apartments', 'loc' => 'Ulubari', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1450, 'rent' => 27000, 'deposit' => 81000, 'society' => 2900, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1730, 'lng' => 91.7590, 'floor' => 5, 'total_floors' => 7, 'owner_idx' => 16],
            ['code' => 'GAU-0019', 'building' => 'Kalpataru Complex', 'loc' => 'Hatigaon', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Unfurnished', 'sqft' => 1000, 'rent' => 17000, 'deposit' => 51000, 'society' => 2000, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1360, 'lng' => 91.7820, 'floor' => 2, 'total_floors' => 5, 'owner_idx' => 17],
            ['code' => 'GAU-0020', 'building' => 'Sunrise Meadows', 'loc' => 'Zoo Road', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1550, 'rent' => 29000, 'deposit' => 87000, 'society' => 3100, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1710, 'lng' => 91.7820, 'floor' => 6, 'total_floors' => 9, 'owner_idx' => 0],
            ['code' => 'GAU-0021', 'building' => 'Lakeview Independent Floor', 'loc' => 'Dispur', 'bhk' => '3 BHK', 'type' => 'Builder Floor', 'furnish' => 'Semi-Furnished', 'sqft' => 1600, 'rent' => 28000, 'deposit' => 84000, 'society' => 2000, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1430, 'lng' => 91.7900, 'floor' => 2, 'total_floors' => 3, 'owner_idx' => 1],
            ['code' => 'GAU-0022', 'building' => 'Hill Crest Manor', 'loc' => 'Beltola', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1250, 'rent' => 25000, 'deposit' => 75000, 'society' => 2700, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1330, 'lng' => 91.7940, 'floor' => 4, 'total_floors' => 7, 'owner_idx' => 2],
            ['code' => 'GAU-0023', 'building' => 'Green Valley Villa', 'loc' => 'Khanapara', 'bhk' => '3 BHK', 'type' => 'Villa', 'furnish' => 'Fully Furnished', 'sqft' => 2200, 'rent' => 42000, 'deposit' => 126000, 'society' => 4000, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1170, 'lng' => 91.8230, 'floor' => 1, 'total_floors' => 2, 'owner_idx' => 3],
            ['code' => 'GAU-0024', 'building' => 'Shangrila Tower', 'loc' => 'Christian Basti', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1150, 'rent' => 23000, 'deposit' => 69000, 'society' => 2500, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1600, 'lng' => 91.7780, 'floor' => 8, 'total_floors' => 12, 'owner_idx' => 4],
            ['code' => 'GAU-0025', 'building' => 'Riverfront Studio Suites', 'loc' => 'Bhangagarh', 'bhk' => '1 RK', 'type' => 'Studio', 'furnish' => 'Fully Furnished', 'sqft' => 480, 'rent' => 15000, 'deposit' => 45000, 'society' => 1800, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1630, 'lng' => 91.7660, 'floor' => 3, 'total_floors' => 6, 'owner_idx' => 5],
            ['code' => 'GAU-0026', 'building' => 'Kaziranga Enclave', 'loc' => 'Six Mile', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1480, 'rent' => 26000, 'deposit' => 78000, 'society' => 2800, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1380, 'lng' => 91.8120, 'floor' => 2, 'total_floors' => 8, 'owner_idx' => 6],
            ['code' => 'GAU-0027', 'building' => 'Airport Greens Apartment', 'loc' => 'Azara', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Unfurnished', 'sqft' => 950, 'rent' => 14000, 'deposit' => 42000, 'society' => 1600, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 26.1100, 'lng' => 91.5900, 'floor' => 1, 'total_floors' => 4, 'owner_idx' => 7],

            // 3 Maintenance (26 to 28)
            ['code' => 'GAU-0028', 'building' => 'Pragjyotishpur Heights', 'loc' => 'Dispur', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1100, 'rent' => 21000, 'deposit' => 63000, 'society' => 2400, 'status' => 'Maintenance', 'onboard' => 'Activated', 'lat' => 26.1440, 'lng' => 91.7910, 'floor' => 1, 'total_floors' => 6, 'owner_idx' => 8],
            ['code' => 'GAU-0029', 'building' => 'Pinnacle Point', 'loc' => 'Ganeshguri', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1500, 'rent' => 29000, 'deposit' => 87000, 'society' => 3000, 'status' => 'Maintenance', 'onboard' => 'Activated', 'lat' => 26.1530, 'lng' => 91.7840, 'floor' => 4, 'total_floors' => 7, 'owner_idx' => 9],
            ['code' => 'GAU-0030', 'building' => 'Vista Hermosa', 'loc' => 'Zoo Road', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1200, 'rent' => 23000, 'deposit' => 69000, 'society' => 2600, 'status' => 'Maintenance', 'onboard' => 'Activated', 'lat' => 26.1720, 'lng' => 91.7830, 'floor' => 2, 'total_floors' => 6, 'owner_idx' => 10],

            // 3 Pending Review (29 to 31)
            ['code' => 'GAU-0031', 'building' => 'Daffodil Court', 'loc' => 'Christian Basti', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1650, 'rent' => 33000, 'deposit' => 99000, 'society' => 3500, 'status' => 'Onboarding', 'onboard' => 'Pending Review', 'lat' => 26.1610, 'lng' => 91.7790, 'floor' => 6, 'total_floors' => 10, 'owner_idx' => 11],
            ['code' => 'GAU-0032', 'building' => 'Lotus Valley Independent House', 'loc' => 'Beltola', 'bhk' => '3 BHK', 'type' => 'Independent House', 'furnish' => 'Semi-Furnished', 'sqft' => 1900, 'rent' => 32000, 'deposit' => 96000, 'society' => 1500, 'status' => 'Onboarding', 'onboard' => 'Pending Review', 'lat' => 26.1340, 'lng' => 91.7950, 'floor' => 1, 'total_floors' => 2, 'owner_idx' => 12],
            ['code' => 'GAU-0033', 'building' => 'Magnolia Residency', 'loc' => 'Six Mile', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1180, 'rent' => 24000, 'deposit' => 72000, 'society' => 2600, 'status' => 'Onboarding', 'onboard' => 'Pending Review', 'lat' => 26.1390, 'lng' => 91.8130, 'floor' => 3, 'total_floors' => 7, 'owner_idx' => 13],

            // 2 In Progress (32 to 33)
            ['code' => 'GAU-0034', 'building' => 'Orchid Manor', 'loc' => 'Ulubari', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1080, 'rent' => 21000, 'deposit' => 63000, 'society' => 2300, 'status' => 'Onboarding', 'onboard' => 'In Progress', 'lat' => 26.1740, 'lng' => 91.7600, 'floor' => 1, 'total_floors' => 5, 'owner_idx' => 14],
            ['code' => 'GAU-0035', 'building' => 'Valley View Apartments', 'loc' => 'Hatigaon', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Unfurnished', 'sqft' => 1350, 'rent' => 22000, 'deposit' => 66000, 'society' => 2400, 'status' => 'Onboarding', 'onboard' => 'In Progress', 'lat' => 26.1370, 'lng' => 91.7830, 'floor' => 4, 'total_floors' => 6, 'owner_idx' => 15],

            // 1 Changes Requested (34)
            ['code' => 'GAU-0036', 'building' => 'Heritage Palm Suites', 'loc' => 'Ganeshguri', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1200, 'rent' => 26000, 'deposit' => 78000, 'society' => 2800, 'status' => 'Onboarding', 'onboard' => 'Changes Requested', 'review_notes' => 'Please provide clear photos of master bedroom balcony and re-confirm APDCL meter number.', 'lat' => 26.1540, 'lng' => 91.7850, 'floor' => 2, 'total_floors' => 7, 'owner_idx' => 16],
        ];

        foreach ($gauConfigs as $cfg) {
            $definitions[] = [
                'code' => $cfg['code'],
                'building_name' => $cfg['building'],
                'address_line_1' => "Flat " . ($cfg['floor'] * 100 + rand(1, 4)) . ", {$cfg['building']}, {$cfg['loc']}",
                'city_id' => $this->cities['Guwahati']->id,
                'city_name' => 'Guwahati',
                'locality_id' => $gauLocs[$cfg['loc']] ?? array_values($gauLocs)[0],
                'pincode' => '781005',
                'landmark' => "Near {$cfg['loc']} Junction",
                'latitude' => $cfg['lat'],
                'longitude' => $cfg['lng'],
                'property_type_id' => $this->propertyTypes[$cfg['type']] ?? array_values($this->propertyTypes)[0],
                'property_type_name' => $cfg['type'],
                'bhk_type_id' => $this->bhkTypes[$cfg['bhk']] ?? array_values($this->bhkTypes)[0],
                'bhk_name' => $cfg['bhk'],
                'floor' => $cfg['floor'],
                'total_floors' => $cfg['total_floors'],
                'sqft' => $cfg['sqft'],
                'flooring_type_id' => $this->flooringTypes['Vitrified'] ?? array_values($this->flooringTypes)[0],
                'furnishing_type_id' => $this->furnishingTypes[$cfg['furnish']] ?? array_values($this->furnishingTypes)[0],
                'furnishing_name' => $cfg['furnish'],
                'property_status' => $cfg['status'],
                'onboarding_status' => $cfg['onboard'],
                'rent' => $cfg['rent'],
                'deposit' => $cfg['deposit'],
                'society_fee' => $cfg['society'],
                'owner_index' => $cfg['owner_idx'],
                'tenant_index' => $cfg['tenant_idx'] ?? null,
                'review_notes' => $cfg['review_notes'] ?? null,
            ];
        }

        // --- 15 BANGALORE PROPERTIES ---
        $blrConfigs = [
            // 5 Vacant (35 to 39)
            ['code' => 'BLR-0001', 'building' => 'Prestige Ferns Residency', 'loc' => 'HSR Layout', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1750, 'rent' => 52000, 'deposit' => 250000, 'society' => 5500, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 12.9121, 'lng' => 77.6445, 'floor' => 7, 'total_floors' => 18, 'owner_idx' => 18],
            ['code' => 'BLR-0002', 'building' => 'Sobha Dream Acres', 'loc' => 'Whitefield', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1210, 'rent' => 38000, 'deposit' => 180000, 'society' => 4200, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 12.9698, 'lng' => 77.7499, 'floor' => 11, 'total_floors' => 22, 'owner_idx' => 19],
            ['code' => 'BLR-0003', 'building' => 'Brigade Cornerstone Utopia', 'loc' => 'Whitefield', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1620, 'rent' => 58000, 'deposit' => 280000, 'society' => 6000, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 12.9710, 'lng' => 77.7510, 'floor' => 14, 'total_floors' => 26, 'owner_idx' => 20],
            ['code' => 'BLR-0004', 'building' => 'Godrej Eternity', 'loc' => 'Koramangala', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1350, 'rent' => 48000, 'deposit' => 220000, 'society' => 4800, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 12.9352, 'lng' => 77.6245, 'floor' => 4, 'total_floors' => 12, 'owner_idx' => 21],
            ['code' => 'BLR-0005', 'building' => 'Purva Windermere Villa', 'loc' => 'Bellandur', 'bhk' => '4 BHK', 'type' => 'Villa', 'furnish' => 'Semi-Furnished', 'sqft' => 3100, 'rent' => 95000, 'deposit' => 450000, 'society' => 8500, 'status' => 'Vacant', 'onboard' => 'Activated', 'lat' => 12.9260, 'lng' => 77.6762, 'floor' => 1, 'total_floors' => 2, 'owner_idx' => 22],

            // 1 Maintenance (40)
            ['code' => 'BLR-0006', 'building' => 'Salarpuria Sattva Greenage', 'loc' => 'Koramangala', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1800, 'rent' => 62000, 'deposit' => 300000, 'society' => 6200, 'status' => 'Maintenance', 'onboard' => 'Activated', 'lat' => 12.9360, 'lng' => 77.6250, 'floor' => 9, 'total_floors' => 24, 'owner_idx' => 23],

            // 2 Pending Review (41 to 42)
            ['code' => 'BLR-0007', 'building' => 'Total Environment Learning to Fly', 'loc' => 'Jayanagar', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 2400, 'rent' => 85000, 'deposit' => 400000, 'society' => 8000, 'status' => 'Onboarding', 'onboard' => 'Pending Review', 'lat' => 12.9308, 'lng' => 77.5838, 'floor' => 12, 'total_floors' => 20, 'owner_idx' => 24],
            ['code' => 'BLR-0008', 'building' => 'Assetz Marq Studio', 'loc' => 'Whitefield', 'bhk' => '1 RK', 'type' => 'Studio', 'furnish' => 'Fully Furnished', 'sqft' => 520, 'rent' => 28000, 'deposit' => 120000, 'society' => 2800, 'status' => 'Onboarding', 'onboard' => 'Pending Review', 'lat' => 12.9720, 'lng' => 77.7520, 'floor' => 5, 'total_floors' => 15, 'owner_idx' => 25],

            // 2 In Progress (43 to 44)
            ['code' => 'BLR-0009', 'building' => 'Divyasree Republic of Whitefield', 'loc' => 'Whitefield', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1100, 'rent' => 36000, 'deposit' => 160000, 'society' => 3800, 'status' => 'Onboarding', 'onboard' => 'In Progress', 'lat' => 12.9730, 'lng' => 77.7530, 'floor' => 6, 'total_floors' => 18, 'owner_idx' => 26],
            ['code' => 'BLR-0010', 'building' => 'Embassy Pristine Penthouse', 'loc' => 'Bellandur', 'bhk' => '4 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 3400, 'rent' => 110000, 'deposit' => 500000, 'society' => 11000, 'status' => 'Onboarding', 'onboard' => 'In Progress', 'lat' => 12.9270, 'lng' => 77.6770, 'floor' => 16, 'total_floors' => 17, 'owner_idx' => 27],

            // 2 Changes Requested (45 to 46)
            ['code' => 'BLR-0011', 'building' => 'Rohan Ikebana', 'loc' => 'Indiranagar', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 1280, 'rent' => 46000, 'deposit' => 200000, 'society' => 4500, 'status' => 'Onboarding', 'onboard' => 'Changes Requested', 'review_notes' => 'BESCOM electricity consumer account number mismatch with bill proof.', 'lat' => 12.9784, 'lng' => 77.6408, 'floor' => 3, 'total_floors' => 8, 'owner_idx' => 18],
            ['code' => 'BLR-0012', 'building' => 'Adarsh Palm Retreat Villa', 'loc' => 'Bellandur', 'bhk' => '4 BHK', 'type' => 'Villa', 'furnish' => 'Fully Furnished', 'sqft' => 3600, 'rent' => 125000, 'deposit' => 600000, 'society' => 12000, 'status' => 'Onboarding', 'onboard' => 'Changes Requested', 'review_notes' => 'Please upload clear photos of private garden and modular kitchen appliances.', 'lat' => 12.9280, 'lng' => 77.6780, 'floor' => 1, 'total_floors' => 2, 'owner_idx' => 19],

            // 3 Draft (47 to 49)
            ['code' => 'BLR-0013', 'building' => 'Vaswani Reserve', 'loc' => 'Indiranagar', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Semi-Furnished', 'sqft' => 1850, 'rent' => 65000, 'deposit' => 320000, 'society' => 6500, 'status' => 'Draft', 'onboard' => 'Draft', 'lat' => 12.9790, 'lng' => 77.6420, 'floor' => 4, 'total_floors' => 10, 'owner_idx' => 20],
            ['code' => 'BLR-0014', 'building' => 'Vaishnavi Oasis', 'loc' => 'HSR Layout', 'bhk' => '2 BHK', 'type' => 'Apartment', 'furnish' => 'Unfurnished', 'sqft' => 1150, 'rent' => 34000, 'deposit' => 150000, 'society' => 3500, 'status' => 'Draft', 'onboard' => 'Draft', 'lat' => 12.9130, 'lng' => 77.6450, 'floor' => 2, 'total_floors' => 8, 'owner_idx' => 21],
            ['code' => 'BLR-0015', 'building' => 'Mantri Espana Luxury Suites', 'loc' => 'Koramangala', 'bhk' => '3 BHK', 'type' => 'Apartment', 'furnish' => 'Fully Furnished', 'sqft' => 2100, 'rent' => 78000, 'deposit' => 380000, 'society' => 7500, 'status' => 'Draft', 'onboard' => 'Draft', 'lat' => 12.9370, 'lng' => 77.6260, 'floor' => 8, 'total_floors' => 16, 'owner_idx' => 22],
        ];

        foreach ($blrConfigs as $cfg) {
            $definitions[] = [
                'code' => $cfg['code'],
                'building_name' => $cfg['building'],
                'address_line_1' => "Tower " . chr(65 + rand(0, 3)) . ", Flat " . ($cfg['floor'] * 100 + rand(1, 4)) . ", {$cfg['building']}, {$cfg['loc']}",
                'city_id' => $this->cities['Bangalore']->id,
                'city_name' => 'Bangalore',
                'locality_id' => $blrLocs[$cfg['loc']] ?? array_values($blrLocs)[0],
                'pincode' => '560034',
                'landmark' => "Near {$cfg['loc']} Main Road",
                'latitude' => $cfg['lat'],
                'longitude' => $cfg['lng'],
                'property_type_id' => $this->propertyTypes[$cfg['type']] ?? array_values($this->propertyTypes)[0],
                'property_type_name' => $cfg['type'],
                'bhk_type_id' => $this->bhkTypes[$cfg['bhk']] ?? array_values($this->bhkTypes)[0],
                'bhk_name' => $cfg['bhk'],
                'floor' => $cfg['floor'],
                'total_floors' => $cfg['total_floors'],
                'sqft' => $cfg['sqft'],
                'flooring_type_id' => $this->flooringTypes['Vitrified'] ?? array_values($this->flooringTypes)[0],
                'furnishing_type_id' => $this->furnishingTypes[$cfg['furnish']] ?? array_values($this->furnishingTypes)[0],
                'furnishing_name' => $cfg['furnish'],
                'property_status' => $cfg['status'],
                'onboarding_status' => $cfg['onboard'],
                'rent' => $cfg['rent'],
                'deposit' => $cfg['deposit'],
                'society_fee' => $cfg['society'],
                'owner_index' => $cfg['owner_idx'],
                'review_notes' => $cfg['review_notes'] ?? null,
            ];
        }

        return $definitions;
    }

    protected function seedRoomsForProperty(Property $property, string $bhkName, int $totalSqft): void
    {
        $rooms = [];

        // Living Room & Kitchen are standard
        $livingDefId = $this->roomDefinitions['Living Room'] ?? array_values($this->roomDefinitions)[0];
        $kitchenDefId = $this->roomDefinitions['Modular Kitchen'] ?? ($this->roomDefinitions['Kitchen'] ?? array_values($this->roomDefinitions)[0]);
        $bathDefId = $this->roomDefinitions['Attached Bathroom'] ?? array_values($this->roomDefinitions)[0];
        $masterBedDefId = $this->roomDefinitions['Master Bedroom'] ?? array_values($this->roomDefinitions)[0];
        $secondBedDefId = $this->roomDefinitions['Second Bedroom'] ?? array_values($this->roomDefinitions)[0];
        $thirdBedDefId = $this->roomDefinitions['Third Bedroom'] ?? array_values($this->roomDefinitions)[0];
        $balconyDefId = $this->roomDefinitions['Front Balcony'] ?? array_values($this->roomDefinitions)[0];

        $rooms[] = ['def' => $livingDefId, 'name' => 'Living Room', 'area' => round($totalSqft * 0.35)];
        $rooms[] = ['def' => $kitchenDefId, 'name' => 'Modular Kitchen', 'area' => round($totalSqft * 0.15)];
        $rooms[] = ['def' => $masterBedDefId, 'name' => 'Master Bedroom', 'area' => round($totalSqft * 0.25)];
        $rooms[] = ['def' => $bathDefId, 'name' => 'Attached Bathroom', 'area' => 60];

        if (in_array($bhkName, ['2 BHK', '3 BHK', '4 BHK', '5+ BHK'])) {
            $rooms[] = ['def' => $secondBedDefId, 'name' => 'Second Bedroom', 'area' => round($totalSqft * 0.20)];
            $rooms[] = ['def' => $bathDefId, 'name' => 'Common Bathroom', 'area' => 55];
            $rooms[] = ['def' => $balconyDefId, 'name' => 'Living Balcony', 'area' => 50];
        }

        if (in_array($bhkName, ['3 BHK', '4 BHK', '5+ BHK'])) {
            $rooms[] = ['def' => $thirdBedDefId, 'name' => 'Guest Bedroom', 'area' => round($totalSqft * 0.18)];
        }

        foreach ($rooms as $order => $r) {
            PropertyRoom::firstOrCreate(
                [
                    'property_id' => $property->id,
                    'custom_name' => $r['name'],
                ],
                [
                    'room_definition_id' => $r['def'],
                    'area' => $r['area'],
                    'display_order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function seedInventoryForProperty(Property $property, string $furnishingName): void
    {
        // 1. Mandatory 'keys' inventory item for all properties
        $keysTypeId = $this->inventoryTypes['keys'] ?? null;
        if ($keysTypeId) {
            PropertyInventory::firstOrCreate(
                ['property_id' => $property->id, 'inventory_type_id' => $keysTypeId, 'property_room_id' => null],
                ['count' => 3]
            );
        }

        // 2. Furnishing items
        if ($furnishingName === 'Fully Furnished') {
            $items = ['fan' => 4, 'light' => 8, 'sofa' => 1, 'bed' => 2, 'wardrobe' => 2, 'air-conditioner' => 2, 'fridge' => 1, 'geyser' => 2, 'dining-set' => 1];
            foreach ($items as $slug => $count) {
                if (isset($this->inventoryTypes[$slug])) {
                    PropertyInventory::firstOrCreate(
                        ['property_id' => $property->id, 'inventory_type_id' => $this->inventoryTypes[$slug], 'property_room_id' => null],
                        ['count' => $count]
                    );
                }
            }
        } elseif ($furnishingName === 'Semi-Furnished') {
            $items = ['fan' => 3, 'light' => 6, 'wardrobe' => 1, 'geyser' => 1, 'kitchen-cabinet' => 1];
            foreach ($items as $slug => $count) {
                if (isset($this->inventoryTypes[$slug])) {
                    PropertyInventory::firstOrCreate(
                        ['property_id' => $property->id, 'inventory_type_id' => $this->inventoryTypes[$slug], 'property_room_id' => null],
                        ['count' => $count]
                    );
                }
            }
        }
    }

    protected function seedUtilitiesForProperty(Property $property, string $cityName): void
    {
        $provider = $cityName === 'Guwahati' ? 'APDCL' : 'BESCOM';

        // Electricity
        if (isset($this->utilityTypes['electricity'])) {
            PropertyUtility::firstOrCreate(
                ['property_id' => $property->id, 'utility_type_id' => $this->utilityTypes['electricity']],
                [
                    'paid_by' => 'tenant',
                    'effective_from' => now()->subMonths(3)->toDateString(),
                    'details' => "Provider: {$provider}, Consumer No: " . rand(10000000, 99999999),
                ]
            );
        }

        // Water
        if (isset($this->utilityTypes['water'])) {
            PropertyUtility::firstOrCreate(
                ['property_id' => $property->id, 'utility_type_id' => $this->utilityTypes['water']],
                [
                    'paid_by' => 'owner',
                    'effective_from' => now()->subMonths(3)->toDateString(),
                    'details' => '24x7 Society Borewell & Municipal Supply',
                ]
            );
        }
    }

    protected function seedAmenitiesForProperty(Property $property, string $propertyTypeName): void
    {
        $amenitiesToAttach = ['Lift', 'Power Backup', 'Security', 'Parking'];

        if ($propertyTypeName === 'Apartment' || $propertyTypeName === 'Villa') {
            $amenitiesToAttach[] = 'Gym';
            $amenitiesToAttach[] = 'Club House';
        }

        foreach ($amenitiesToAttach as $amenityName) {
            if (isset($this->amenityTypes[$amenityName])) {
                PropertyAmenity::firstOrCreate(
                    ['property_id' => $property->id, 'amenity_type_id' => $this->amenityTypes[$amenityName]],
                    ['notes' => 'Available for all residents.']
                );
            }
        }
    }

    protected function seedEstablishmentsForProperty(Property $property, string $cityName): void
    {
        $estIds = $this->establishments[$cityName] ?? [];
        if (empty($estIds)) {
            return;
        }

        // Pick 2 nearby establishments
        $selected = array_slice($estIds, 0, 2);
        foreach ($selected as $idx => $estId) {
            $dist = round(0.8 + ($idx * 1.5) + (rand(1, 10) / 10), 1);
            PropertyEstablishment::firstOrCreate(
                ['property_id' => $property->id, 'establishment_id' => $estId],
                [
                    'distance_km' => $dist,
                    'travel_time_minutes' => (int) round($dist * 4),
                    'remarks' => 'Quick access via main arterial road.',
                ]
            );
        }
    }

    protected function generateBasePdfContent(): string
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Memorandum of Understanding</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; margin: 20px; }
        .header { border-bottom: 2px solid #0284c7; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #0f172a; margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0 0; color: #64748b; font-size: 10px; }
        .badge { background-color: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 10px; display: inline-block; }
        .section-title { font-size: 13px; font-weight: bold; color: #0284c7; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-top: 20px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table td { padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 10px; }
        table td.label { background-color: #f8fafc; font-weight: bold; width: 30%; color: #334155; }
        .footer { margin-top: 40px; border-top: 1px solid #cbd5e1; padding-top: 15px; font-size: 9px; color: #94a3b8; }
        .signatures { margin-top: 30px; }
        .sig-block { width: 45%; display: inline-block; vertical-align: top; border-top: 1px dashed #94a3b8; padding-top: 8px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dwelly Realtech Private Limited</h1>
        <p>Asset Management & Tenancy Facilitation Services</p>
        <div style="margin-top: 8px;">
            <span class="badge">LEGALLY EXECUTED &amp; VERIFIED MOU</span>
        </div>
    </div>

    <div class="section-title">1. Memorandum of Understanding Overview</div>
    <p>This Memorandum of Understanding (&quot;MOU&quot;) sets forth the terms and conditions under which Dwelly Realtech Private Limited (&quot;Manager&quot;) manages and facilitates the leasing, tenant placement, and operations of the residential property for the Owner.</p>

    <div class="section-title">2. Commercial &amp; Financial Structure</div>
    <table>
        <tr>
            <td class="label">Management Model</td>
            <td>Rent Share (8% Dwelly Management &amp; Operational Fee)</td>
        </tr>
        <tr>
            <td class="label">Fee Collection</td>
            <td>Auto-deducted from monthly rental collections before owner payout disbursement.</td>
        </tr>
        <tr>
            <td class="label">Legal Jurisdiction</td>
            <td>Civil Courts of Jurisdiction governing the property location.</td>
        </tr>
    </table>

    <div class="section-title">3. Execution &amp; Attestation</div>
    <p>Both parties confirm that the property verification, inventory baseline, utility meter records, and title documents have been submitted and digitally countersigned.</p>

    <div class="signatures">
        <div class="sig-block">
            <strong>For Dwelly Realtech Pvt Ltd</strong><br/>
            Authorized Signatory<br/>
            Legal &amp; Compliance Division
        </div>
        <div class="sig-block" style="float: right;">
            <strong>For Property Owner / Signatory</strong><br/>
            Digitally Signed &amp; Acknowledged<br/>
            Verified via Aadhaar OTP / Stamped Agreement
        </div>
    </div>

    <div class="footer">
        Dwelly Property Management System &bull; Generated digitally for record preservation &bull; Document Reference: MOU-DWELLY-PROD
    </div>
</body>
</html>
HTML;

        return Pdf::loadHTML($html)->output();
    }
}
