<?php

namespace App\Domain\Property\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\Locality;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunitySource;
use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyAddress;
use App\Domain\Party\Models\PartyBankAccount;
use App\Domain\Party\Models\PartyIndividual;
use App\Domain\Property\Models\AmenityType;
use App\Domain\Property\Models\FurnishingType;
use App\Domain\Property\Models\InventoryType;
use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyAmenity;
use App\Domain\Property\Models\PropertyFinancialTerm;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyPhoto;
use App\Domain\Property\Models\PropertyPricingVersion;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyType;
use App\Domain\Property\Models\PropertyUtility;
use App\Domain\Property\Models\RoomDefinition;
use App\Domain\Property\Models\UtilityType;
use App\Domain\Shared\Services\NumberingService;
use App\Models\Branch;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tek2991\Accounting\Models\State;

class PropertyCsvImportService
{
    protected ?User $adminUser = null;
    protected ?User $executiveUser = null;
    protected ?User $reviewerUser = null;

    protected array $cities = [];
    protected array $branches = [];
    protected array $bhkTypes = [];
    protected array $propertyTypes = [];
    protected array $furnishingTypes = [];
    protected array $flooringTypes = [];
    protected array $roomDefinitions = [];
    protected array $inventoryTypes = [];
    protected array $utilityTypes = [];
    protected array $amenityTypes = [];
    protected array $opportunitySources = [];
    protected array $financialModels = [];

    protected array $requiredHeaders = [
        'building_name',
        'address_line_1',
        'locality',
        'city',
        'pincode',
        'bhk_type',
        'property_type',
        'furnishing_type',
        'property_status',
        'owner_name',
        'owner_phone',
        'owner_email',
        'rent_amount',
        'security_deposit',
    ];

    public function __construct()
    {
        // Initialized lazily upon execution
    }

    /**
     * Parse and import properties from a CSV file.
     *
     * @param string $filePath Absolute path or path relative to base_path
     * @param bool $dryRun If true, validates rows without committing changes
     * @return array
     */
    public function import(string $filePath, bool $dryRun = false, ?string $specsFilePath = null, ?string $tenantsFilePath = null): array
    {
        $realPath = file_exists($filePath) ? $filePath : base_path($filePath);

        if (!file_exists($realPath) || !is_readable($realPath)) {
            return [
                'success' => false,
                'message' => "CSV file not found or not readable: {$filePath}",
                'total_rows' => 0,
                'imported_rows' => 0,
                'errors' => ["File [{$filePath}] does not exist."],
                'warnings' => [],
            ];
        }

        $rows = $this->parseCsv($realPath);
        if (empty($rows)) {
            return [
                'success' => false,
                'message' => 'The CSV file is empty or could not be parsed.',
                'total_rows' => 0,
                'imported_rows' => 0,
                'errors' => ['No valid data rows found.'],
                'warnings' => [],
            ];
        }

        // Load optional tenants map (keyed by property_code)
        $tenantsMap = $this->resolveTenantsMap($realPath, $tenantsFilePath);

        $validation = $this->validateRows($rows, $tenantsMap);
        if (!empty($validation['errors'])) {
            return [
                'success' => false,
                'message' => 'CSV validation failed with ' . count($validation['errors']) . ' error(s).',
                'total_rows' => count($rows),
                'imported_rows' => 0,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ];
        }

        if ($dryRun) {
            return [
                'success' => true,
                'message' => 'Dry run validation succeeded for ' . count($rows) . ' rows.',
                'total_rows' => count($rows),
                'imported_rows' => 0,
                'errors' => [],
                'warnings' => $validation['warnings'],
                'rows_summary' => $validation['summary'],
            ];
        }

        // Initialize Reference Data
        $this->loadSystemReferences();

        // Load optional specifications map
        $specsMap = $this->resolveSpecificationsMap($realPath, $specsFilePath);

        $imported = 0;
        $executionErrors = [];
        $results = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Row 1 is header
            try {
                $property = DB::transaction(function () use ($row, $rowNumber, $realPath, $specsMap, $tenantsMap) {
                    return $this->processRow($row, $rowNumber, dirname($realPath), $specsMap, $tenantsMap);
                });

                $imported++;
                $results[] = [
                    'row' => $rowNumber,
                    'code' => $property->code,
                    'building_name' => $property->building_name,
                    'status' => $property->status,
                    'has_custom_specs' => isset($specsMap[$property->code]),
                ];
            } catch (\Throwable $e) {
                Log::error("CSV Property Import failed on row {$rowNumber}: " . $e->getMessage(), [
                    'row' => $row,
                    'exception' => $e,
                ]);
                $executionErrors[] = "Row {$rowNumber} [{$row['building_name']}]: " . $e->getMessage();
            }
        }

        return [
            'success' => empty($executionErrors),
            'message' => "Successfully imported {$imported} of " . count($rows) . " properties.",
            'total_rows' => count($rows),
            'imported_rows' => $imported,
            'errors' => $executionErrors,
            'warnings' => $validation['warnings'],
            'data' => $results,
        ];
    }

    /**
     * Parse CSV into an array of associative rows.
     */
    public function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        // Read and strip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [];
        }

        // Clean headers
        $cleanHeaders = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h)));
        }, $headers);

        while (($data = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($data, fn ($val) => trim($val) !== ''))) {
                continue;
            }

            // Pad or slice data to match headers length
            if (count($data) < count($cleanHeaders)) {
                $data = array_pad($data, count($cleanHeaders), '');
            } elseif (count($data) > count($cleanHeaders)) {
                $data = array_slice($data, 0, count($cleanHeaders));
            }

            $row = array_combine($cleanHeaders, array_map('trim', $data));
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Validate CSV rows and headers.
     */
    protected function validateRows(array $rows, array $tenantsMap = []): array
    {
        $errors = [];
        $warnings = [];
        $summary = [];

        if (empty($rows)) {
            $errors[] = 'CSV file contains no rows.';
            return compact('errors', 'warnings', 'summary');
        }

        $firstRow = $rows[0];
        foreach ($this->requiredHeaders as $header) {
            if (!array_key_exists($header, $firstRow)) {
                $errors[] = "Missing required column header: '{$header}'";
            }
        }

        if (!empty($errors)) {
            return compact('errors', 'warnings', 'summary');
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            // Required basic fields
            foreach ($this->requiredHeaders as $field) {
                if (empty($row[$field])) {
                    $errors[] = "Row {$rowNumber}: '{$field}' is required and cannot be empty.";
                }
            }

            // City validation
            $city = ucfirst(strtolower($row['city'] ?? ''));
            if (!in_array($city, ['Guwahati', 'Bangalore'])) {
                $errors[] = "Row {$rowNumber}: Invalid city '{$row['city']}'. Must be 'Guwahati' or 'Bangalore'.";
            }

            // Status validation
            $status = ucfirst(strtolower($row['property_status'] ?? ''));
            if (!in_array($status, ['Occupied', 'Vacant', 'Under Maintenance', 'Under Notice', 'Draft'])) {
                $warnings[] = "Row {$rowNumber}: Property status '{$row['property_status']}' is non-standard, defaulting to Vacant.";
            }

            // If occupied, tenant fields or tenant CSV rows are required
            if ($status === 'Occupied') {
                $propCode = strtoupper(trim($row['property_code'] ?? ''));
                $hasTenantsInCsv = !empty($propCode) && !empty($tenantsMap[$propCode]);
                $hasInlineTenant = !empty($row['tenant_name']) && !empty($row['tenant_phone']);

                if (!$hasTenantsInCsv && !$hasInlineTenant) {
                    $errors[] = "Row {$rowNumber}: 'tenant_name' is required when property_status is 'Occupied' (or tenant records must exist in tenants CSV for '{$row['building_name']}').";
                }
            }

            // Signing authority validation
            $isSignatoryDiff = filter_var($row['is_signatory_different'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isSignatoryDiff && empty($row['signatory_name'])) {
                $errors[] = "Row {$rowNumber}: 'signatory_name' is required when 'is_signatory_different' is true.";
            }

            // Numeric validations
            if (!empty($row['rent_amount']) && !is_numeric($row['rent_amount'])) {
                $errors[] = "Row {$rowNumber}: 'rent_amount' must be numeric. Found '{$row['rent_amount']}'.";
            }
            if (!empty($row['security_deposit']) && !is_numeric($row['security_deposit'])) {
                $errors[] = "Row {$rowNumber}: 'security_deposit' must be numeric. Found '{$row['security_deposit']}'.";
            }

            $summary[] = [
                'row' => $rowNumber,
                'building' => $row['building_name'] ?? 'N/A',
                'city' => $city,
                'owner' => $row['owner_name'] ?? 'N/A',
                'status' => $status,
            ];
        }

        return compact('errors', 'warnings', 'summary');
    }

    /**
     * Process a single validated CSV row into domain records.
     */
    protected function processRow(array $row, int $rowNumber, ?string $csvDir = null, array $specsMap = [], array $tenantsMap = []): Property
    {
        $cityName = ucfirst(strtolower($row['city']));
        $stateAssam = State::where('name', 'Assam')->first()?->id;
        $stateKarnataka = State::where('name', 'Karnataka')->first()?->id;
        $stateId = ($cityName === 'Guwahati') ? $stateAssam : $stateKarnataka;
        $stateName = $row['state'] ?: ($cityName === 'Guwahati' ? 'Assam' : 'Karnataka');

        $city = $this->cities[$cityName] ?? City::firstOrCreate(['name' => $cityName]);
        $branchId = $this->branches[$cityName] ?? 1;

        // 1. Resolve Locality
        $localityName = trim($row['locality']);
        $locality = Locality::firstOrCreate(
            ['city_id' => $city->id, 'name' => $localityName],
            ['pincode' => $row['pincode'] ?? '781001']
        );

        // 2. Resolve Reference Types
        $bhkName = trim($row['bhk_type']);
        $bhkTypeId = $this->bhkTypes[$bhkName] ?? ($this->bhkTypes['2 BHK'] ?? array_values($this->bhkTypes)[0] ?? null);

        $propTypeName = trim($row['property_type']);
        $propTypeId = $this->propertyTypes[$propTypeName] ?? ($this->propertyTypes['Apartment'] ?? array_values($this->propertyTypes)[0] ?? null);

        $furnishName = trim($row['furnishing_type']);
        $furnishTypeId = $this->furnishingTypes[$furnishName] ?? ($this->furnishingTypes['Semi-Furnished'] ?? array_values($this->furnishingTypes)[0] ?? null);

        $flooringTypeId = !empty($row['flooring_type']) && isset($this->flooringTypes[$row['flooring_type']])
            ? $this->flooringTypes[$row['flooring_type']]
            : ($this->flooringTypes['Vitrified'] ?? array_values($this->flooringTypes)[0] ?? null);

        // 3. Resolve Owner Party
        $ownerParty = $this->resolveOwnerParty($row, $stateId, $stateName);

        // 4. Create or Update Property
        $propCode = !empty($row['property_code']) ? trim($row['property_code']) : NumberingService::generate('property');
        $propStatus = ucfirst(strtolower($row['property_status'] ?? 'Vacant'));
        if (!in_array($propStatus, ['Occupied', 'Vacant', 'Under Maintenance', 'Under Notice', 'Draft'])) {
            $propStatus = 'Vacant';
        }

        $property = Property::firstOrNew(['code' => $propCode]);
        $property->fill([
            'branch_id' => $branchId,
            'code' => $propCode,
            'building_name' => $row['building_name'],
            'address_line_1' => $row['address_line_1'],
            'address_line_2' => $row['address_line_2'] ?: null,
            'locality_id' => $locality->id,
            'locality' => $locality->name,
            'city' => $cityName,
            'state' => $stateName,
            'pincode' => $row['pincode'],
            'bhk_type_id' => $bhkTypeId,
            'property_type_id' => $propTypeId,
            'furnishing_type_id' => $furnishTypeId,
            'flooring_type_id' => $flooringTypeId,
            'floor' => !empty($row['floor']) ? (int) $row['floor'] : 1,
            'total_floors' => !empty($row['total_floors']) ? (int) $row['total_floors'] : 4,
            'floor_space_sqft' => !empty($row['floor_space_sqft']) ? (int) $row['floor_space_sqft'] : 1000,
            'status' => $propStatus,
            'is_listed' => filter_var($row['is_listed'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_promoted' => false,
            'available_from' => !empty($row['available_from']) ? $row['available_from'] : now()->toDateString(),
            'assigned_executive_id' => $this->executiveUser?->id,
        ]);
        $property->save();

        // 5. Seed Rooms, Inventory, Utilities, Amenities
        if (isset($specsMap[$propCode])) {
            $this->applyPropertySpecifications($property, $specsMap[$propCode]);
        } else {
            $this->seedRoomsForProperty($property, $bhkName, (int) $property->floor_space_sqft);
            $this->seedInventoryForProperty($property, $furnishName);
            $this->seedAmenitiesForProperty($property, $propTypeName);
        }

        $this->seedUtilitiesForProperty($property, $cityName);

        // 6. Create Opportunity & MOU linking Property to Owner
        $rent = (float) $row['rent_amount'];
        $deposit = (float) $row['security_deposit'];
        $societyFee = !empty($row['society_fee']) ? (float) $row['society_fee'] : 0.0;
        $feePct = !empty($row['mou_fee_percentage']) ? (float) $row['mou_fee_percentage'] : 8.0;

        $isRentSharing = isset($row['is_rent_sharing'])
            ? filter_var($row['is_rent_sharing'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $appliedFeePct = $isRentSharing ? $feePct : 0.0;
        $financialModelName = $isRentSharing ? 'Rent share' : 'Annual subscription';
        $financialModelId = $this->financialModels[$financialModelName]
            ?? ($this->financialModels[$isRentSharing ? 'rent-share' : 'annual-subscription'] ?? null)
            ?? (array_values($this->financialModels)[0] ?? null);

        $mouNumber = NumberingService::generate('mou');
        $mouStartDate = !empty($row['mou_start_date']) ? $row['mou_start_date'] : now()->subMonths(3)->toDateString();

        $opportunity = Opportunity::firstOrCreate(
            ['number' => "OPP-{$propCode}"],
            [
                'branch_id' => $branchId,
                'title' => "{$bhkName} at {$property->building_name} - {$ownerParty->display_name}",
                'status' => 'converted',
                'opportunity_source_id' => $this->opportunitySources[array_rand($this->opportunitySources)] ?? null,
                'assigned_user_id' => $this->executiveUser?->id,
                'owner_party_id' => $ownerParty->id,
                'owner_name' => $ownerParty->display_name,
                'owner_phone' => $ownerParty->phone,
                'owner_email' => $ownerParty->email,
                'address' => $property->address_line_1,
                'expected_rent' => $rent,
                'expected_financial_model_id' => $financialModelId,
            ]
        );

        $isSignatoryDiff = filter_var($row['is_signatory_different'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($isSignatoryDiff) {
            $signatoryDetails = [
                'name' => trim($row['signatory_name'] ?? ''),
                'relation' => trim($row['signatory_relation'] ?? 'POA Holder'),
                'phone' => trim($row['signatory_phone'] ?? ''),
                'email' => trim($row['signatory_email'] ?? ''),
                'aadhar_number' => trim($row['signatory_aadhaar'] ?? ($row['signatory_aadhaar_number'] ?? '')),
                'pan_number' => strtoupper(trim($row['signatory_pan'] ?? ($row['signatory_pan_number'] ?? ''))),
            ];
        } else {
            $signatoryDetails = [
                'name' => $ownerParty->display_name,
                'relation' => 'Self',
                'phone' => $ownerParty->phone,
                'email' => $ownerParty->email,
                'aadhar_number' => $ownerParty->individual?->aadhaar_number ?? ($row['owner_aadhaar'] ?? null),
                'pan_number' => $ownerParty->individual?->pan_number ?? ($row['owner_pan'] ?? null),
            ];
        }

        $mou = Mou::firstOrNew(['property_id' => $property->id]);
        $mou->fill([
            'number' => $mou->exists ? $mou->number : $mouNumber,
            'branch_id' => $branchId,
            'opportunity_id' => $opportunity->id,
            'property_id' => $property->id,
            'party_id' => $ownerParty->id,
            'type' => MouType::ONBOARDING,
            'status' => MouStatus::CONVERTED,
            'version' => 1,
            'start_date' => $mouStartDate,
            'owner_details' => [
                'party_id' => $ownerParty->id,
                'party_type' => 'individual',
                'name' => $ownerParty->display_name,
                'phone' => $ownerParty->phone,
                'email' => $ownerParty->email,
                'pan_number' => $ownerParty->individual?->pan_number ?? 'ABCDE1234F',
                'aadhar_number' => $ownerParty->individual?->aadhaar_number ?? '987654321012',
                'address' => $property->address_line_1,
                'city' => $cityName,
                'state' => $stateName,
            ],
            'signatory_details' => $signatoryDetails,
            'is_signatory_different' => $isSignatoryDiff,
            'legal_terms' => [
                'rent_amount' => $rent,
                'security_deposit' => $deposit,
                'society_fee' => $societyFee,
                'fee_percentage' => $appliedFeePct,
                'city_id' => $city->id,
                'city_name' => $cityName,
                'address' => $property->address_line_1,
                'financial_model_id' => $financialModelId,
                'financial_model_name' => $financialModelName,
                'is_rent_sharing' => $isRentSharing,
            ],
            'bank_details' => [
                'bank_name' => $row['owner_bank_name'] ?? 'HDFC Bank',
                'beneficiary_name' => $ownerParty->display_name,
                'account_number' => $row['owner_bank_account'] ?? ('50100' . rand(1000000, 9999999)),
                'ifsc_code' => $row['owner_bank_ifsc'] ?? 'HDFC0001234',
                'account_type' => 'Savings',
            ],
            'verified_at' => now()->subMonths(2),
            'verified_by' => $this->reviewerUser?->id,
            'prepared_by' => $this->executiveUser?->id,
            'generated_by' => $this->adminUser?->id,
        ]);
        $mou->save();

        // Resolve Property Folder (e.g. storage/app/seed_assets/properties/GAU-0091 or database/seeders/data/properties/GAU-0091)
        $propDir = $this->findPropertyFolder($propCode, $csvDir);

        // Attach MOU Media
        $this->attachMouDocument($mou, $property, $row['mou_pdf_file'] ?? null, $propDir);

        // Attach Signatory Media if different
        $this->attachSignatoryDocuments($mou, $row, $propDir);

        // 7. Create Onboarding Project & Financial Terms
        $onboarding = OnboardingProject::firstOrNew(['property_id' => $property->id]);
        $onboarding->fill([
            'status' => 'Activated',
            'assigned_executive_id' => $this->executiveUser?->id,
            'reviewer_id' => $this->reviewerUser?->id,
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(2),
            'review_notes' => 'Existing legacy property imported via CSV.',
        ]);
        $onboarding->save();

        PropertyFinancialTerm::firstOrCreate(
            ['property_id' => $property->id],
            [
                'mou_id' => $mou->id,
                'pricing_model' => $financialModelName,
                'fee_percentage' => $appliedFeePct,
                'effective_from' => $mouStartDate,
                'created_by' => $this->adminUser?->id,
            ]
        );

        PropertyPricingVersion::firstOrCreate(
            ['property_id' => $property->id, 'effective_from' => $mouStartDate],
            [
                'rent' => $rent,
                'security_deposit' => $deposit,
                'society_fee' => $societyFee,
                'booking_amount' => round($rent * 0.25, -2),
                'notes' => 'Legacy imported base pricing.',
                'created_by' => $this->adminUser?->id,
            ]
        );

        // 8. Create Tenancy Agreement if Occupied or Tenant specified
        $propertyTenants = $tenantsMap[strtoupper($propCode)] ?? [];
        if ($propStatus === 'Occupied' || !empty($propertyTenants) || !empty($row['tenant_name'])) {
            $this->createTenancyAgreementForProperty(
                $property,
                $row,
                $propertyTenants,
                $branchId,
                $rent,
                $deposit,
                $stateId,
                $stateName,
                $propDir
            );
        }

        // 9. Attach Property Photos
        $this->attachPropertyPhotos($property, $row['photo_files'] ?? null, $propDir);

        return $property;
    }

    /**
     * Resolve or create the Owner Party with identity and bank details.
     */
    protected function resolveOwnerParty(array $row, ?int $stateId, string $stateName): Party
    {
        $ownerEmail = trim($row['owner_email']);
        $ownerPhone = trim($row['owner_phone']);
        $ownerName = trim($row['owner_name']);

        $party = Party::where('email', $ownerEmail)
            ->orWhere('phone', $ownerPhone)
            ->first();

        if (!$party) {
            $party = Party::create([
                'party_type' => 'individual',
                'display_name' => $ownerName,
                'phone' => $ownerPhone,
                'email' => $ownerEmail,
                'state_id' => $stateId,
            ]);
        }

        PartyIndividual::firstOrCreate(
            ['party_id' => $party->id],
            [
                'name' => $ownerName,
                'pan_number' => $row['owner_pan'] ?? ('PAN' . rand(10000, 99999) . 'Z'),
                'aadhaar_number' => $row['owner_aadhaar'] ?? ('98' . rand(1000000000, 9999999999)),
            ]
        );

        if (!empty($row['address_line_1'])) {
            PartyAddress::firstOrCreate(
                ['party_id' => $party->id, 'is_primary' => true],
                [
                    'type' => 'permanent',
                    'address_line_1' => $row['address_line_1'],
                    'address_line_2' => $row['address_line_2'] ?: null,
                    'city' => $row['city'],
                    'state' => $stateName,
                    'pincode' => $row['pincode'],
                    'country' => 'India',
                ]
            );
        }

        $party->enableRole(BusinessRole::OWNER);

        if (!empty($row['owner_bank_account'])) {
            PartyBankAccount::firstOrCreate(
                ['party_id' => $party->id, 'account_number' => $row['owner_bank_account']],
                [
                    'beneficiary_name' => $ownerName,
                    'bank_name' => $row['owner_bank_name'] ?? 'HDFC Bank',
                    'ifsc_code' => $row['owner_bank_ifsc'] ?? 'HDFC0001234',
                    'is_primary' => true,
                    'is_verified' => true,
                ]
            );
        }

        try {
            app(AccountingProvisioningService::class)->ensurePartyAccountingReady($party);
        } catch (\Throwable $e) {
            Log::warning("Accounting provisioning deferred for Owner {$ownerName}: " . $e->getMessage());
        }

        return $party;
    }

    /**
     * Create Tenancy Agreement, Tenant Party, and Role.
     */
    protected function createTenancyAgreement(Property $property, array $row, int $branchId, float $rent, float $deposit, ?int $stateId, string $stateName, ?string $propDir = null): TenancyAgreement
    {
        $tenantEmail = trim($row['tenant_email'] ?? 'tenant@example.com');
        $tenantPhone = trim($row['tenant_phone'] ?? '9999999999');
        $tenantName = trim($row['tenant_name'] ?? 'Primary Tenant');

        $tenant = Party::where('email', $tenantEmail)
            ->orWhere('phone', $tenantPhone)
            ->first();

        if (!$tenant) {
            $tenant = Party::create([
                'party_type' => 'individual',
                'display_name' => $tenantName,
                'phone' => $tenantPhone,
                'email' => $tenantEmail,
                'state_id' => $stateId,
            ]);
        }

        PartyIndividual::firstOrCreate(
            ['party_id' => $tenant->id],
            [
                'name' => $tenantName,
                'pan_number' => $row['tenant_pan'] ?? ('TNPAN' . rand(1000, 9999) . 'X'),
                'aadhaar_number' => $row['tenant_aadhaar'] ?? ('12' . rand(1000000000, 9999999999)),
            ]
        );

        if (!empty($property->address_line_1)) {
            PartyAddress::firstOrCreate(
                ['party_id' => $tenant->id, 'is_primary' => true],
                [
                    'type' => 'current',
                    'address_line_1' => $property->address_line_1,
                    'address_line_2' => $property->address_line_2 ?: null,
                    'city' => $property->city,
                    'state' => $stateName,
                    'pincode' => $property->pincode,
                    'country' => 'India',
                ]
            );
        }

        $tenant->enableRole(BusinessRole::TENANT);

        try {
            app(AccountingProvisioningService::class)->ensurePartyAccountingReady($tenant);
        } catch (\Throwable $e) {
            Log::warning("Accounting provisioning deferred for Tenant {$tenantName}: " . $e->getMessage());
        }

        $pricingVersion = $property->pricingVersions()->latest()->first();
        $agreementCode = NumberingService::generate('tenancy');
        $startDate = !empty($row['agreement_start_date']) ? $row['agreement_start_date'] : '2026-01-01';
        $endDate = !empty($row['agreement_end_date']) ? $row['agreement_end_date'] : '2026-12-31';

        $agreement = TenancyAgreement::firstOrCreate(
            ['property_id' => $property->id],
            [
                'branch_id' => $branchId,
                'code' => $agreementCode,
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rent_amount' => $rent,
                'security_deposit' => $deposit,
                'lock_in_period_months' => !empty($row['lock_in_months']) ? (int) $row['lock_in_months'] : 6,
                'notice_period_days' => !empty($row['notice_period_days']) ? (int) $row['notice_period_days'] : 30,
                'pricing_version_id' => $pricingVersion?->id,
                'keys_handed_over' => true,
                'keys_handed_over_at' => Carbon::parse($startDate)->toDateTimeString(),
                'key_handover_notes' => 'Master key set handed over to primary tenant upon lease execution.',
                'signed_at' => Carbon::parse($startDate)->subDays(3)->toDateTimeString(),
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

        // Attach Tenancy Agreement Media
        $this->attachTenancyDocument($agreement, $row['agreement_pdf_file'] ?? null, $propDir);

        return $agreement;
    }

    /**
     * Create Tenancy Agreement with Primary and Secondary Tenants from dedicated tenants CSV.
     */
    protected function createTenancyAgreementForProperty(
        Property $property,
        array $row,
        array $propertyTenants,
        int $branchId,
        float $rent,
        float $deposit,
        ?int $stateId,
        string $stateName,
        ?string $propDir = null
    ): TenancyAgreement {
        if (empty($propertyTenants)) {
            return $this->createTenancyAgreement($property, $row, $branchId, $rent, $deposit, $stateId, $stateName, $propDir);
        }

        $agreementCode = NumberingService::generate('tenancy');
        $pricingVersion = $property->pricingVersions()->latest()->first();

        $primaryTenantRow = null;
        $secondaryTenantRows = [];

        foreach ($propertyTenants as $tRow) {
            $isPrimary = filter_var($tRow['is_primary_tenant'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isPrimary && $primaryTenantRow === null) {
                $primaryTenantRow = $tRow;
            } else {
                $secondaryTenantRows[] = $tRow;
            }
        }

        if ($primaryTenantRow === null) {
            $primaryTenantRow = array_shift($secondaryTenantRows);
        }

        $startDate = !empty($primaryTenantRow['agreement_start_date']) ? $primaryTenantRow['agreement_start_date'] : '2026-01-01';
        $endDate = !empty($primaryTenantRow['agreement_end_date']) ? $primaryTenantRow['agreement_end_date'] : Carbon::parse($startDate)->addMonths(12)->subDay()->toDateString();
        $agRent = !empty($primaryTenantRow['rent_amount']) ? (float) $primaryTenantRow['rent_amount'] : $rent;
        $agDeposit = !empty($primaryTenantRow['security_deposit']) ? (float) $primaryTenantRow['security_deposit'] : $deposit;
        $lockIn = !empty($primaryTenantRow['lock_in_months']) ? (int) $primaryTenantRow['lock_in_months'] : 6;
        $notice = !empty($primaryTenantRow['notice_period_days']) ? (int) $primaryTenantRow['notice_period_days'] : 30;
        $agreementPdf = $primaryTenantRow['agreement_pdf_file'] ?? ($row['agreement_pdf_file'] ?? null);

        // 1. Primary Tenant Party
        $tName = trim($primaryTenantRow['name'] ?? 'Primary Tenant');
        $tPhone = trim($primaryTenantRow['phone'] ?? '9999999999');
        $tEmail = trim($primaryTenantRow['email'] ?? 'tenant@example.com');
        $tPan = trim($primaryTenantRow['pan'] ?? ($primaryTenantRow['pad'] ?? ($primaryTenantRow['pan_number'] ?? '')));
        $tAadhaar = trim($primaryTenantRow['aadhaar'] ?? ($primaryTenantRow['aadhaar_number'] ?? ''));
        $tParent = trim($primaryTenantRow['parent_name'] ?? '');
        $tVoter = trim($primaryTenantRow['voter_id'] ?? '');
        $tAddress = trim($primaryTenantRow['address'] ?? ($property->address_line_1));

        $primaryParty = Party::where('email', $tEmail)->whereNotNull('email')
            ->orWhere('phone', $tPhone)->whereNotNull('phone')
            ->first();

        if (!$primaryParty) {
            $primaryParty = Party::create([
                'party_type' => 'individual',
                'display_name' => $tName,
                'phone' => $tPhone,
                'email' => $tEmail,
                'state_id' => $stateId,
            ]);
        }

        PartyIndividual::firstOrCreate(
            ['party_id' => $primaryParty->id],
            [
                'name' => $tName,
                'pan_number' => $tPan ?: ('TNPAN' . rand(1000, 9999) . 'X'),
                'aadhaar_number' => $tAadhaar ?: ('12' . rand(1000000000, 9999999999)),
                'parent_name' => $tParent ?: null,
                'voter_id' => $tVoter ?: null,
            ]
        );

        if (!empty($tAddress)) {
            PartyAddress::firstOrCreate(
                ['party_id' => $primaryParty->id, 'is_primary' => true],
                [
                    'type' => 'current',
                    'address_line_1' => $tAddress,
                    'city' => $property->city,
                    'state' => $stateName,
                    'pincode' => $property->pincode,
                    'country' => 'India',
                ]
            );
        }

        $primaryParty->enableRole(BusinessRole::TENANT);

        try {
            app(AccountingProvisioningService::class)->ensurePartyAccountingReady($primaryParty);
        } catch (\Throwable $e) {
            Log::warning("Accounting provisioning deferred for Primary Tenant {$tName}: " . $e->getMessage());
        }

        // 2. Secondary Tenants JSON & Parties
        $secondaryArray = [];
        $secondaryParties = [];

        foreach ($secondaryTenantRows as $secRow) {
            $secName = trim($secRow['name'] ?? 'Secondary Tenant');
            $secRel = trim($secRow['relationship'] ?? 'Co-Tenant');
            $secPhone = trim($secRow['phone'] ?? '');
            $secEmail = trim($secRow['email'] ?? '');
            $secPan = trim($secRow['pan'] ?? ($secRow['pad'] ?? ($secRow['pan_number'] ?? '')));
            $secAadhaar = trim($secRow['aadhaar'] ?? ($secRow['aadhaar_number'] ?? ''));
            $secVoter = trim($secRow['voter_id'] ?? '');
            $secAddress = trim($secRow['address'] ?? ($property->address_line_1));

            $secondaryArray[] = [
                'name' => $secName,
                'relationship' => $secRel,
                'phone' => $secPhone,
                'email' => $secEmail,
                'aadhaar_number' => $secAadhaar,
                'pan_number' => $secPan,
                'voter_id' => $secVoter,
                'photo_file' => $secRow['photo_file'] ?? null,
                'aadhaar_file' => $secRow['aadhaar_file'] ?? null,
                'pan_file' => $secRow['pan_file'] ?? null,
                'voter_id_file' => $secRow['voter_id_file'] ?? null,
            ];

            $secParty = null;
            if (!empty($secEmail) || !empty($secPhone)) {
                $secPartyQuery = Party::query();
                if (!empty($secEmail)) {
                    $secPartyQuery->where('email', $secEmail);
                }
                if (!empty($secPhone)) {
                    $secPartyQuery->orWhere('phone', $secPhone);
                }
                $secParty = $secPartyQuery->first();
            }

            if (!$secParty) {
                $secParty = Party::create([
                    'party_type' => 'individual',
                    'display_name' => $secName,
                    'phone' => $secPhone ?: null,
                    'email' => $secEmail ?: null,
                    'state_id' => $stateId,
                ]);
            }

            PartyIndividual::firstOrCreate(
                ['party_id' => $secParty->id],
                [
                    'name' => $secName,
                    'pan_number' => $secPan ?: null,
                    'aadhaar_number' => $secAadhaar ?: null,
                    'voter_id' => $secVoter ?: null,
                ]
            );

            if (!empty($secAddress)) {
                PartyAddress::firstOrCreate(
                    ['party_id' => $secParty->id, 'is_primary' => true],
                    [
                        'type' => 'current',
                        'address_line_1' => $secAddress,
                        'city' => $property->city,
                        'state' => $stateName,
                        'pincode' => $property->pincode,
                        'country' => 'India',
                    ]
                );
            }

            $secParty->enableRole(BusinessRole::TENANT);
            $secondaryParties[] = ['party' => $secParty, 'role' => $secRel ?: 'Co-Tenant'];
        }

        // 3. Create Tenancy Agreement
        $agreement = TenancyAgreement::firstOrCreate(
            ['property_id' => $property->id],
            [
                'branch_id' => $branchId,
                'code' => $agreementCode,
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rent_amount' => $agRent,
                'security_deposit' => $agDeposit,
                'lock_in_period_months' => $lockIn,
                'notice_period_days' => $notice,
                'pricing_version_id' => $pricingVersion?->id,
                'secondary_tenants' => $secondaryArray,
                'keys_handed_over' => true,
                'keys_handed_over_at' => Carbon::parse($startDate)->toDateTimeString(),
                'key_handover_notes' => 'Master key set handed over upon lease execution.',
                'signed_at' => Carbon::parse($startDate)->subDays(3)->toDateTimeString(),
                'signed_by_tenant' => true,
            ]
        );

        if (!empty($secondaryArray) && empty($agreement->secondary_tenants)) {
            $agreement->update(['secondary_tenants' => $secondaryArray]);
        }

        // 4. Attach Primary Role
        TenancyRole::firstOrCreate(
            [
                'tenancy_agreement_id' => $agreement->id,
                'party_id' => $primaryParty->id,
            ],
            [
                'role_type' => 'Primary Tenant',
                'is_primary' => true,
            ]
        );

        // 5. Attach Secondary Roles
        foreach ($secondaryParties as $sp) {
            TenancyRole::firstOrCreate(
                [
                    'tenancy_agreement_id' => $agreement->id,
                    'party_id' => $sp['party']->id,
                ],
                [
                    'role_type' => $sp['role'],
                    'is_primary' => false,
                ]
            );
        }

        // 6. Attach Tenancy PDF Media
        $this->attachTenancyDocument($agreement, $agreementPdf, $propDir);

        return $agreement;
    }

    /**
     * Attach signatory documents (POA, Aadhaar, PAN) to MOU.
     */
    protected function attachSignatoryDocuments(Mou $mou, array $row, ?string $propDir = null): void
    {
        if (!filter_var($row['is_signatory_different'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (!$mou->hasMedia('signatory_poa')) {
            $poaPath = !empty($row['signatory_poa_file'])
                ? $this->resolveAssetPath($row['signatory_poa_file'], 'mous', $propDir)
                : ($propDir ? ($this->findFileInDir($propDir, ['signatory_poa.pdf', 'poa.pdf', 'power_of_attorney.pdf'])
                    ?: $this->findFileByPattern($propDir, '/(poa|power_of_attorney).*\.pdf$/i')) : null);

            if ($poaPath && file_exists($poaPath)) {
                $mou->addMedia($poaPath)->preservingOriginal()->toMediaCollection('signatory_poa');
            }
        }

        if (!$mou->hasMedia('signatory_aadhaar')) {
            $aadhaarPath = !empty($row['signatory_aadhaar_file'])
                ? $this->resolveAssetPath($row['signatory_aadhaar_file'], 'mous', $propDir)
                : ($propDir ? ($this->findFileInDir($propDir, ['signatory_aadhaar.pdf', 'signatory_aadhaar.jpg', 'poa_aadhaar.pdf'])
                    ?: $this->findFileByPattern($propDir, '/signatory_aadhaar.*\.(pdf|jpg|png)$/i')) : null);

            if ($aadhaarPath && file_exists($aadhaarPath)) {
                $mou->addMedia($aadhaarPath)->preservingOriginal()->toMediaCollection('signatory_aadhaar');
            }
        }

        if (!$mou->hasMedia('signatory_pan')) {
            $panPath = !empty($row['signatory_pan_file'])
                ? $this->resolveAssetPath($row['signatory_pan_file'], 'mous', $propDir)
                : ($propDir ? ($this->findFileInDir($propDir, ['signatory_pan.pdf', 'signatory_pan.jpg', 'poa_pan.pdf'])
                    ?: $this->findFileByPattern($propDir, '/signatory_pan.*\.(pdf|jpg|png)$/i')) : null);

            if ($panPath && file_exists($panPath)) {
                $mou->addMedia($panPath)->preservingOriginal()->toMediaCollection('signatory_pan');
            }
        }
    }

    /**
     * Attach signed PDF file or generate DomPDF document for MOU.
     */
    protected function attachMouDocument(Mou $mou, Property $property, ?string $specifiedPath = null, ?string $propDir = null): void
    {
        if ($mou->hasMedia('signed_pdf')) {
            return;
        }

        $resolvedPath = null;

        // 1. Check folder auto-discovery: {propDir}/mou.pdf or *mou*.pdf
        if ($propDir) {
            $resolvedPath = $this->findFileInDir($propDir, ['mou.pdf', 'mou_signed.pdf', 'signed_mou.pdf', 'mou.PDF'])
                ?: $this->findFileByPattern($propDir, '/mou.*\.pdf$/i');
        }

        // 2. Check explicit path from CSV as override
        if (!$resolvedPath && !empty($specifiedPath)) {
            $resolvedPath = $this->resolveAssetPath($specifiedPath, 'mous', $propDir);
        }

        if ($resolvedPath && file_exists($resolvedPath)) {
            $mou->addMedia($resolvedPath)
                ->preservingOriginal()
                ->toMediaCollection('signed_pdf');
        } else {
            // Generate clean base DomPDF
            $pdfContent = $this->generateSampleMouPdf($mou);
            $mou->addMediaFromString($pdfContent)
                ->usingFileName("{$mou->number}-signed.pdf")
                ->toMediaCollection('signed_pdf');
        }
    }

    /**
     * Attach signed PDF file or generate DomPDF document for Tenancy Agreement.
     */
    protected function attachTenancyDocument(TenancyAgreement $agreement, ?string $specifiedPath = null, ?string $propDir = null): void
    {
        if ($agreement->hasMedia('signed_agreement')) {
            return;
        }

        $resolvedPath = null;

        // 1. Check folder auto-discovery: {propDir}/tenancy.pdf or agreement.pdf or *tenan*.pdf or *agree*.pdf
        if ($propDir) {
            $resolvedPath = $this->findFileInDir($propDir, ['tenancy.pdf', 'agreement.pdf', 'lease.pdf', 'signed_agreement.pdf', 'signed_tenancy.pdf'])
                ?: $this->findFileByPattern($propDir, '/(tenan|agree|lease).*\.pdf$/i');
        }

        // 2. Check explicit path from CSV as override
        if (!$resolvedPath && !empty($specifiedPath)) {
            $resolvedPath = $this->resolveAssetPath($specifiedPath, 'agreements', $propDir);
        }

        if ($resolvedPath && file_exists($resolvedPath)) {
            $agreement->addMedia($resolvedPath)
                ->preservingOriginal()
                ->toMediaCollection('signed_agreement');
        } else {
            // Generate clean base DomPDF
            $pdfContent = $this->generateSampleAgreementPdf($agreement);
            $agreement->addMediaFromString($pdfContent)
                ->usingFileName("{$agreement->code}-signed.pdf")
                ->toMediaCollection('signed_agreement');
        }
    }

    /**
     * Attach property photos from property folder or CSV or seed default exterior photo.
     */
    protected function attachPropertyPhotos(Property $property, ?string $photoFiles = null, ?string $propDir = null): void
    {
        if ($property->photos()->count() > 0) {
            return;
        }

        $photoPaths = [];

        // 1. Check folder auto-discovery: {propDir}/photos/ or {propDir}/Photos/ or images directly in {propDir}/
        if ($propDir) {
            $photosSubdir = null;
            foreach (['photos', 'Photos', 'images', 'Images'] as $sub) {
                if (is_dir("{$propDir}/{$sub}")) {
                    $photosSubdir = "{$propDir}/{$sub}";
                    break;
                }
            }

            $searchDir = $photosSubdir ?: $propDir;
            $files = File::files($searchDir);
            $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            foreach ($files as $file) {
                if (in_array(strtolower($file->getExtension()), $imageExtensions)) {
                    $photoPaths[] = $file->getRealPath();
                }
            }
            sort($photoPaths);
        }

        // 2. Fallback to explicit CSV photo_files if no folder photos found
        if (empty($photoPaths) && !empty($photoFiles)) {
            foreach (explode(';', $photoFiles) as $pPath) {
                $pPath = trim($pPath);
                if (!empty($pPath)) {
                    $resolved = $this->resolveAssetPath($pPath, 'photos', $propDir);
                    if ($resolved && file_exists($resolved)) {
                        $photoPaths[] = $resolved;
                    }
                }
            }
        }

        // 3. Copy files and create records
        if (!empty($photoPaths)) {
            foreach ($photoPaths as $index => $resolved) {
                $filename = "{$property->code}_" . basename($resolved);
                $destinationPath = "property-photos/{$filename}";

                Storage::disk('public')->put($destinationPath, file_get_contents($resolved));

                PropertyPhoto::create([
                    'property_id' => $property->id,
                    'property_room_id' => null,
                    'file_path' => $destinationPath,
                    'title' => ucwords(str_replace(['_', '-'], ' ', pathinfo($resolved, PATHINFO_FILENAME))),
                    'is_featured' => ($index === 0),
                    'is_visible' => true,
                    'order_column' => $index + 1,
                ]);
            }
            return;
        }

        // 4. Fallback placeholder SVG if no photos found anywhere
        $filename = "{$property->code}_exterior.svg";
        $destinationPath = "property-photos/{$filename}";
        $svgContent = $this->generatePlaceholderSvg($property->building_name, $property->code);

        Storage::disk('public')->put($destinationPath, $svgContent);

        PropertyPhoto::create([
            'property_id' => $property->id,
            'property_room_id' => null,
            'file_path' => $destinationPath,
            'title' => 'Exterior Building Elevation & Entrance',
            'is_featured' => true,
            'is_visible' => true,
            'order_column' => 1,
        ]);
    }

    /**
     * Locate property asset directory by code.
     */
    protected function findPropertyFolder(string $propertyCode, ?string $csvDir = null): ?string
    {
        $code = trim($propertyCode);
        $candidates = [
            storage_path("app/seed_assets/properties/{$code}"),
            storage_path("app/seed_assets/{$code}"),
            base_path("database/seeders/data/properties/{$code}"),
        ];

        if ($csvDir) {
            $candidates[] = "{$csvDir}/properties/{$code}";
            $candidates[] = "{$csvDir}/{$code}";
        }

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    protected function findFileInDir(string $dir, array $filenames): ?string
    {
        $files = File::files($dir);
        foreach ($filenames as $targetName) {
            foreach ($files as $file) {
                if (strcasecmp($file->getFilename(), $targetName) === 0) {
                    return $file->getRealPath();
                }
            }
        }
        return null;
    }

    protected function findFileByPattern(string $dir, string $regex): ?string
    {
        foreach (File::files($dir) as $file) {
            if (preg_match($regex, $file->getFilename())) {
                return $file->getRealPath();
            }
        }
        return null;
    }

    /**
     * Resolve asset path checking multiple storage locations.
     */
    protected function resolveAssetPath(?string $path, string $subfolder, ?string $propDir = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        $candidates = [
            $path,
        ];

        if ($propDir) {
            $candidates[] = "{$propDir}/" . basename($path);
            $candidates[] = "{$propDir}/{$path}";
        }

        $candidates[] = storage_path("app/seed_assets/{$subfolder}/" . basename($path));
        $candidates[] = storage_path("app/seed_assets/{$path}");
        $candidates[] = storage_path("app/{$path}");
        $candidates[] = public_path($path);
        $candidates[] = base_path($path);

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Load references into memory.
     */
    protected function loadSystemReferences(): void
    {
        $this->cities['Guwahati'] = City::where('name', 'Guwahati')->first();
        $this->cities['Bangalore'] = City::where('name', 'Bangalore')->first();

        $this->branches['Guwahati'] = Branch::where('code', 'GHY')->orWhere('city', 'Guwahati')->value('id') ?? 1;
        $this->branches['Bangalore'] = Branch::where('code', 'BLR')->orWhere('city', 'Bangalore')->value('id') ?? 2;

        $this->bhkTypes = DB::table('bhk_types')->pluck('id', 'name')->toArray();
        $this->propertyTypes = PropertyType::pluck('id', 'name')->toArray();
        $this->furnishingTypes = FurnishingType::pluck('id', 'name')->toArray();
        $this->flooringTypes = DB::table('flooring_types')->pluck('id', 'name')->toArray();
        $this->financialModels = FinancialModel::pluck('id', 'name')->toArray();
        $this->opportunitySources = OpportunitySource::pluck('id')->toArray();
        $this->utilityTypes = UtilityType::pluck('id', 'slug')->toArray();
        $this->inventoryTypes = InventoryType::pluck('id', 'slug')->toArray();
        $this->amenityTypes = AmenityType::pluck('id', 'name')->toArray();
        $this->roomDefinitions = RoomDefinition::pluck('id', 'name')->toArray();

        $this->adminUser = User::where('email', 'admin@dwelly.in')->first() ?? User::first();
        $this->executiveUser = User::where('email', 'rahul.operations@dwelly.in')->first() ?? $this->adminUser;
        $this->reviewerUser = User::where('email', 'ananya.reviewer@dwelly.in')->first() ?? $this->adminUser;
    }

    protected function seedRoomsForProperty(Property $property, string $bhkName, int $totalSqft): void
    {
        $livingDefId = $this->roomDefinitions['Living Room'] ?? array_values($this->roomDefinitions)[0] ?? null;
        $kitchenDefId = $this->roomDefinitions['Modular Kitchen'] ?? ($this->roomDefinitions['Kitchen'] ?? array_values($this->roomDefinitions)[0] ?? null);
        $bathDefId = $this->roomDefinitions['Attached Bathroom'] ?? array_values($this->roomDefinitions)[0] ?? null;
        $masterBedDefId = $this->roomDefinitions['Master Bedroom'] ?? array_values($this->roomDefinitions)[0] ?? null;
        $secondBedDefId = $this->roomDefinitions['Second Bedroom'] ?? array_values($this->roomDefinitions)[0] ?? null;

        if (!$livingDefId) {
            return;
        }

        $rooms = [
            ['def' => $livingDefId, 'name' => 'Living Room', 'area' => round($totalSqft * 0.35)],
            ['def' => $kitchenDefId, 'name' => 'Modular Kitchen', 'area' => round($totalSqft * 0.15)],
            ['def' => $masterBedDefId, 'name' => 'Master Bedroom', 'area' => round($totalSqft * 0.25)],
            ['def' => $bathDefId, 'name' => 'Attached Bathroom', 'area' => 60],
        ];

        if (in_array($bhkName, ['2 BHK', '3 BHK', '4 BHK', '5+ BHK'])) {
            $rooms[] = ['def' => $secondBedDefId, 'name' => 'Second Bedroom', 'area' => round($totalSqft * 0.20)];
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
        $keysTypeId = $this->inventoryTypes['keys'] ?? null;
        if ($keysTypeId) {
            PropertyInventory::firstOrCreate(
                ['property_id' => $property->id, 'inventory_type_id' => $keysTypeId, 'property_room_id' => null],
                ['count' => 3]
            );
        }

        if ($furnishingName === 'Fully Furnished') {
            $items = ['fan' => 4, 'light' => 8, 'sofa' => 1, 'bed' => 2, 'wardrobe' => 2, 'geyser' => 2];
            foreach ($items as $slug => $count) {
                if (isset($this->inventoryTypes[$slug])) {
                    PropertyInventory::firstOrCreate(
                        ['property_id' => $property->id, 'inventory_type_id' => $this->inventoryTypes[$slug], 'property_room_id' => null],
                        ['count' => $count]
                    );
                }
            }
        } elseif ($furnishingName === 'Semi-Furnished') {
            $items = ['fan' => 3, 'light' => 6, 'wardrobe' => 1, 'geyser' => 1];
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

        if (isset($this->utilityTypes['water'])) {
            PropertyUtility::firstOrCreate(
                ['property_id' => $property->id, 'utility_type_id' => $this->utilityTypes['water']],
                [
                    'paid_by' => 'owner',
                    'effective_from' => now()->subMonths(3)->toDateString(),
                    'details' => '24x7 Society Borewell Supply',
                ]
            );
        }
    }

    protected function seedAmenitiesForProperty(Property $property, string $propertyTypeName): void
    {
        $amenitiesToAttach = ['Lift', 'Power Backup', 'Security', 'Parking'];
        if (in_array($propertyTypeName, ['Apartment', 'Villa'])) {
            $amenitiesToAttach[] = 'Gym';
        }

        foreach ($amenitiesToAttach as $amenityName) {
            $amenityTypeId = $this->amenityTypes[$amenityName] ?? null;
            if ($amenityTypeId) {
                PropertyAmenity::firstOrCreate([
                    'property_id' => $property->id,
                    'amenity_type_id' => $amenityTypeId,
                ]);
            }
        }
    }

    /**
     * Generate HTML and PDF content for an authentic signed MOU.
     */
    protected function generateSampleMouPdf(Mou $mou): string
    {
        $owner = $mou->owner_details['name'] ?? 'Property Owner';
        $prop = $mou->property;
        $address = $prop?->address_line_1 ?? 'Property Address';
        $city = $prop?->city ?? 'City';
        $rent = number_format($mou->legal_terms['rent_amount'] ?? 0, 2);
        $deposit = number_format($mou->legal_terms['security_deposit'] ?? 0, 2);
        $mouNumber = $mou->number;

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Memorandum of Understanding - {$mouNumber}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; margin: 20px; }
        .header { border-bottom: 2px solid #0284c7; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #0f172a; margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0 0; color: #64748b; font-size: 10px; }
        .badge { background-color: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 10px; display: inline-block; margin-top: 5px; }
        .section-title { font-size: 13px; font-weight: bold; color: #0284c7; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-top: 20px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table td { padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 10px; }
        table td.label { background-color: #f8fafc; font-weight: bold; width: 35%; color: #334155; }
        .signatures { margin-top: 30px; }
        .sig-block { width: 45%; display: inline-block; vertical-align: top; border-top: 1px dashed #94a3b8; padding-top: 8px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dwelly Realtech Private Limited</h1>
        <p>Asset Management & Tenancy Facilitation Services</p>
        <div><span class="badge">LEGALLY EXECUTED MOU - {$mouNumber}</span></div>
    </div>

    <div class="section-title">1. Property & Owner Particulars</div>
    <table>
        <tr><td class="label">Owner Name</td><td>{$owner}</td></tr>
        <tr><td class="label">Property Address</td><td>{$address}, {$city}</td></tr>
        <tr><td class="label">Agreed Target Rent</td><td>INR {$rent} / month</td></tr>
        <tr><td class="label">Security Deposit</td><td>INR {$deposit}</td></tr>
        <tr><td class="label">Management Fee</td><td>8.0% (Rent Share Model)</td></tr>
    </table>

    <div class="section-title">2. Operational Authorization</div>
    <p>The Owner appoints Dwelly Realtech Pvt Ltd as the exclusive property manager to list, tenant, and collect rents on their behalf.</p>

    <div class="signatures">
        <div class="sig-block">
            <strong>Dwelly Realtech Pvt Ltd</strong><br/>
            Authorized Signatory
        </div>
        <div class="sig-block" style="float: right;">
            <strong>{$owner}</strong><br/>
            Property Owner (Digitally Signed)
        </div>
    </div>
</body>
</html>
HTML;

        return Pdf::loadHTML($html)->output();
    }

    /**
     * Generate HTML and PDF content for an authentic signed Tenancy Agreement.
     */
    protected function generateSampleAgreementPdf(TenancyAgreement $agreement): string
    {
        $prop = $agreement->property;
        $tenantRole = $agreement->primaryTenant;
        $tenantName = $tenantRole?->party?->display_name ?? 'Tenant';
        $building = $prop?->building_name ?? 'Apartment';
        $code = $agreement->code;
        $rent = number_format($agreement->rent_amount, 2);
        $deposit = number_format($agreement->security_deposit, 2);
        $start = $agreement->start_date?->format('d M Y') ?? '01 Jan 2026';
        $end = $agreement->end_date?->format('d M Y') ?? '31 Dec 2026';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tenancy Agreement - {$code}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; margin: 20px; }
        .header { border-bottom: 2px solid #059669; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #065f46; margin: 0; font-size: 18px; text-transform: uppercase; }
        .badge { background-color: #d1fae5; color: #065f46; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 10px; display: inline-block; margin-top: 5px; }
        .section-title { font-size: 13px; font-weight: bold; color: #059669; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-top: 20px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table td { padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 10px; }
        table td.label { background-color: #f8fafc; font-weight: bold; width: 35%; color: #334155; }
        .signatures { margin-top: 30px; }
        .sig-block { width: 45%; display: inline-block; vertical-align: top; border-top: 1px dashed #94a3b8; padding-top: 8px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Leave and License Tenancy Agreement</h1>
        <div><span class="badge">EXECUTED AGREEMENT - {$code}</span></div>
    </div>

    <div class="section-title">1. Key Commercial Terms</div>
    <table>
        <tr><td class="label">Agreement Code</td><td>{$code}</td></tr>
        <tr><td class="label">Primary Tenant</td><td>{$tenantName}</td></tr>
        <tr><td class="label">Property Unit</td><td>{$building}</td></tr>
        <tr><td class="label">Lease Duration</td><td>{$start} to {$end}</td></tr>
        <tr><td class="label">Monthly Rent</td><td>INR {$rent}</td></tr>
        <tr><td class="label">Security Deposit</td><td>INR {$deposit}</td></tr>
        <tr><td class="label">Lock-in Period</td><td>{$agreement->lock_in_period_months} Months</td></tr>
        <tr><td class="label">Notice Period</td><td>{$agreement->notice_period_days} Days</td></tr>
    </table>

    <div class="section-title">2. Possession & Key Handover</div>
    <p>Keys and full possession of the premises have been handed over to the Tenant upon signing of this deed.</p>

    <div class="signatures">
        <div class="sig-block">
            <strong>For Dwelly / Property Owner</strong><br/>
            Authorized Signatory
        </div>
        <div class="sig-block" style="float: right;">
            <strong>{$tenantName}</strong><br/>
            Tenant (Signed &amp; Acknowledged)
        </div>
    </div>
</body>
</html>
HTML;

        return Pdf::loadHTML($html)->output();
    }

    /**
     * Generate an SVG banner image when photos are omitted.
     */
    protected function generatePlaceholderSvg(string $title, string $code): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_XML1);
        $escapedCode = htmlspecialchars($code, ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#0284c7;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#0f172a;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="800" height="600" fill="url(#grad)" />
    <g fill="#ffffff" opacity="0.1">
        <circle cx="400" cy="300" r="240" />
        <rect x="250" y="180" width="300" height="280" rx="15" />
    </g>
    <path d="M 320 400 L 400 320 L 480 400 Z" fill="#38bdf8" opacity="0.6"/>
    <text x="400" y="270" font-family="DejaVu Sans, sans-serif" font-size="28" font-weight="bold" fill="#ffffff" text-anchor="middle">{$escapedTitle}</text>
    <text x="400" y="320" font-family="DejaVu Sans, sans-serif" font-size="16" fill="#93c5fd" text-anchor="middle">Dwelly Managed Property ({$escapedCode})</text>
</svg>
SVG;
    }

    /**
     * Resolve and load specifications map from candidate file paths.
     */
    protected function resolveSpecificationsMap(string $realPropertiesCsvPath, ?string $specsFilePath = null): array
    {
        $resolved = $specsFilePath;
        if (!$resolved) {
            $candidates = [
                dirname($realPropertiesCsvPath) . '/property_specifications_template.csv',
                dirname($realPropertiesCsvPath) . '/property_specifications.csv',
                base_path('database/seeders/data/property_specifications_template.csv'),
                base_path('database/seeders/data/property_specifications.csv'),
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand) && is_readable($cand)) {
                    $resolved = $cand;
                    break;
                }
            }
        }

        if ($resolved && file_exists($resolved)) {
            return $this->loadSpecificationsMap($resolved);
        }

        return [];
    }

    /**
     * Load specifications keyed by property_code.
     */
    public function loadSpecificationsMap(string $filePath): array
    {
        $realPath = file_exists($filePath) ? $filePath : base_path($filePath);
        if (!file_exists($realPath) || !is_readable($realPath)) {
            return [];
        }

        $rows = $this->parseCsv($realPath);
        $map = [];
        foreach ($rows as $row) {
            $code = trim($row['property_code'] ?? '');
            if (!empty($code)) {
                $map[$code] = $row;
            }
        }

        return $map;
    }

    /**
     * Standalone import or update of property specifications (rooms, amenities, inventory).
     */
    public function importSpecifications(string $filePath, bool $dryRun = false): array
    {
        $realPath = file_exists($filePath) ? $filePath : base_path($filePath);

        if (!file_exists($realPath) || !is_readable($realPath)) {
            return [
                'success' => false,
                'message' => "Specifications CSV file not found or not readable: {$filePath}",
                'total_rows' => 0,
                'imported_rows' => 0,
                'errors' => ["File [{$filePath}] does not exist."],
            ];
        }

        $rows = $this->parseCsv($realPath);
        if (empty($rows)) {
            return [
                'success' => false,
                'message' => 'The specifications CSV file is empty.',
                'total_rows' => 0,
                'imported_rows' => 0,
                'errors' => ['No valid data rows found.'],
            ];
        }

        $this->loadSystemReferences();

        $processed = 0;
        $errors = [];
        $summary = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $code = trim($row['property_code'] ?? '');
            if (empty($code)) {
                $errors[] = "Row {$rowNumber}: 'property_code' is required.";
                continue;
            }

            $property = Property::where('code', $code)->first();
            if (!$property) {
                $errors[] = "Row {$rowNumber}: Property with code '{$code}' does not exist.";
                continue;
            }

            if (!$dryRun) {
                try {
                    DB::transaction(function () use ($property, $row) {
                        $this->applyPropertySpecifications($property, $row);
                    });
                    $processed++;
                } catch (\Throwable $e) {
                    $errors[] = "Row {$rowNumber} [{$code}]: " . $e->getMessage();
                }
            } else {
                $processed++;
            }

            $summary[] = [
                'property_code' => $code,
                'building_name' => $property->building_name,
                'rooms_count' => $property->rooms()->count(),
                'amenities_count' => $property->amenities()->count(),
                'inventories_count' => $property->inventories()->count(),
            ];
        }

        return [
            'success' => empty($errors),
            'message' => $dryRun ? "Dry-run validated specifications for {$processed} properties." : "Successfully updated specifications for {$processed} properties.",
            'total_rows' => count($rows),
            'imported_rows' => $processed,
            'errors' => $errors,
            'data' => $summary,
        ];
    }

    /**
     * Apply granular room, amenity, and inventory specifications to a Property.
     */
    public function applyPropertySpecifications(Property $property, array $row): void
    {
        // 1. Rooms
        $roomDefsMap = [
            'room_living_room' => 'Living Room',
            'room_kitchen' => 'Modular Kitchen',
            'room_modular_kitchen' => 'Modular Kitchen',
            'room_master_bedroom' => 'Master Bedroom',
            'room_second_bedroom' => 'Second Bedroom',
            'room_third_bedroom' => 'Third Bedroom',
            'room_guest_bedroom' => 'Guest Bedroom',
            'room_attached_bathroom' => 'Attached Bathroom',
            'room_common_bathroom' => 'Common Bathroom',
            'room_balcony' => 'Front Balcony',
            'room_pooja_room' => 'Pooja Room',
            'room_servant_room' => 'Servant Room',
            'room_study_room' => 'Office Room',
        ];

        $property->rooms()->delete();
        $order = 1;
        $totalSqft = (int) $property->floor_space_sqft ?: 1000;

        foreach ($roomDefsMap as $col => $defName) {
            if (!isset($row[$col])) {
                continue;
            }
            $count = $this->parseQuantity($row[$col]);
            if ($count <= 0) {
                continue;
            }

            $defId = $this->roomDefinitions[$defName] ?? (array_values($this->roomDefinitions)[0] ?? null);
            if (!$defId) {
                continue;
            }

            for ($i = 1; $i <= $count; $i++) {
                $displayName = ($count > 1) ? "{$defName} {$i}" : $defName;
                $area = match ($defName) {
                    'Living Room' => round($totalSqft * 0.30),
                    'Modular Kitchen' => round($totalSqft * 0.15),
                    'Master Bedroom' => round($totalSqft * 0.25),
                    'Second Bedroom', 'Third Bedroom', 'Guest Bedroom' => round($totalSqft * 0.18),
                    'Attached Bathroom', 'Common Bathroom' => 55,
                    'Front Balcony' => 50,
                    default => 80,
                };

                PropertyRoom::create([
                    'property_id' => $property->id,
                    'room_definition_id' => $defId,
                    'custom_name' => $displayName,
                    'area' => $area,
                    'display_order' => $order++,
                    'is_active' => true,
                ]);
            }
        }

        // 2. Amenities
        $amenitiesMap = [
            'amenity_lift' => 'Lift',
            'amenity_power_backup' => 'Power Backup',
            'amenity_security' => 'Security',
            'amenity_parking' => 'Parking',
            'amenity_gym' => 'Gym',
            'amenity_swimming_pool' => 'Swimming Pool',
            'amenity_clubhouse' => 'Club House',
            'amenity_club_house' => 'Club House',
        ];

        $property->amenities()->delete();
        foreach ($amenitiesMap as $col => $amenityName) {
            if (!isset($row[$col])) {
                continue;
            }
            if ($this->parseBoolean($row[$col])) {
                $typeId = $this->amenityTypes[$amenityName] ?? null;
                if ($typeId) {
                    PropertyAmenity::firstOrCreate([
                        'property_id' => $property->id,
                        'amenity_type_id' => $typeId,
                    ]);
                }
            }
        }

        // 3. Inventories
        $invMap = [
            'inv_fan' => 'fan',
            'inv_light' => 'light',
            'inv_ac' => 'air-conditioner',
            'inv_air_conditioner' => 'air-conditioner',
            'inv_bed' => 'bed',
            'inv_wardrobe' => 'wardrobe',
            'inv_sofa' => 'sofa',
            'inv_dining_set' => 'dining-set',
            'inv_geyser' => 'geyser',
            'inv_fridge' => 'fridge',
            'inv_tv' => 'television',
            'inv_television' => 'television',
            'inv_washing_machine' => 'washing-machine',
            'inv_microwave' => 'microwave',
            'inv_water_purifier' => 'water-purifier',
            'inv_chimney' => 'kitchen-chimney',
            'inv_kitchen_chimney' => 'kitchen-chimney',
            'inv_kitchen_cabinet' => 'kitchen-cabinet',
            'inv_keys' => 'keys',
        ];

        $property->inventories()->delete();
        $keysCount = 0;

        foreach ($invMap as $col => $invSlug) {
            if (!isset($row[$col])) {
                continue;
            }
            $qty = $this->parseQuantity($row[$col]);
            if ($qty > 0) {
                if ($invSlug === 'keys') {
                    $keysCount = $qty;
                }
                $invTypeId = $this->inventoryTypes[$invSlug] ?? null;
                if ($invTypeId) {
                    PropertyInventory::create([
                        'property_id' => $property->id,
                        'inventory_type_id' => $invTypeId,
                        'property_room_id' => null,
                        'count' => $qty,
                    ]);
                }
            }
        }

        // Mandatory keys fallback to 3 if not specified
        if ($keysCount === 0 && isset($this->inventoryTypes['keys'])) {
            PropertyInventory::firstOrCreate(
                ['property_id' => $property->id, 'inventory_type_id' => $this->inventoryTypes['keys'], 'property_room_id' => null],
                ['count' => 3]
            );
        }
    }

    protected function parseBoolean(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        $str = strtolower(trim((string) $val));
        return in_array($str, ['1', 'yes', 'y', 'true', 't']);
    }

    protected function parseQuantity(mixed $val): int
    {
        if (is_numeric($val)) {
            return max(0, (int) $val);
        }
        return $this->parseBoolean($val) ? 1 : 0;
    }

    /**
     * Resolve and load tenants map from candidate file paths.
     */
    protected function resolveTenantsMap(string $realPropertiesCsvPath, ?string $tenantsFilePath = null): array
    {
        $resolved = $tenantsFilePath;
        if (!$resolved) {
            $candidates = [
                dirname($realPropertiesCsvPath) . '/existing_tenants_template.csv',
                dirname($realPropertiesCsvPath) . '/existing_tenants.csv',
                dirname($realPropertiesCsvPath) . '/tenants_template.csv',
                dirname($realPropertiesCsvPath) . '/tenants.csv',
                dirname($realPropertiesCsvPath) . '/' . str_replace(['properties', 'property'], ['tenants', 'tenant'], basename($realPropertiesCsvPath)),
                base_path('database/seeders/data/existing_tenants_template.csv'),
                base_path('database/seeders/data/existing_tenants.csv'),
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand) && is_readable($cand)) {
                    $resolved = $cand;
                    break;
                }
            }
        }

        if ($resolved && file_exists($resolved)) {
            return $this->loadTenantsMap($resolved);
        }

        return [];
    }

    /**
     * Load tenants keyed by property_code.
     */
    public function loadTenantsMap(string $filePath): array
    {
        $realPath = file_exists($filePath) ? $filePath : base_path($filePath);
        if (!file_exists($realPath) || !is_readable($realPath)) {
            return [];
        }

        $rows = $this->parseCsv($realPath);
        $map = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim($row['property_code'] ?? ''));
            if (!empty($code)) {
                $map[$code][] = $row;
            }
        }

        return $map;
    }
}
