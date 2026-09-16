<?php

namespace Database\Seeders;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyAddress;
use App\Domain\Party\Models\PartyBankAccount;
use App\Domain\Party\Models\PartyIndividual;
use App\Domain\Party\Models\TenantProfile;
use Illuminate\Database\Seeder;
use Tek2991\Accounting\Models\State;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return array<Party>
     */
    public function run(): array
    {
        return $this->seedTenants();
    }

    /**
     * Seed 15 comprehensive tenant parties with individual identity,
     * tenant profile, bank accounts, addresses, and accounting provisioning.
     *
     * @return array<Party>
     */
    public function seedTenants(): array
    {
        $provisioningService = app(AccountingProvisioningService::class);

        $stateAssam = State::where('name', 'Assam')->first()?->id;
        $stateKarnataka = State::where('name', 'Karnataka')->first()?->id;

        $tenantData = [
            [
                'name' => 'Abhishek Sen',
                'phone' => '9864998877',
                'email' => 'abhishek.sen@tcs.com',
                'pan' => 'ASDPN1122K',
                'parent_name' => 'Dipak Sen',
                'gender' => 'Male',
                'dob' => '1992-05-14',
                'occupation' => 'Lead Consultant',
                'employer' => 'Tata Consultancy Services',
                'income' => 125000.00,
                'emergency_contact_name' => 'Dipak Sen (Father)',
                'emergency_contact_phone' => '9864011223',
                'bank_name' => 'HDFC Bank',
                'account_number' => '5010043891234',
                'ifsc_code' => 'HDFC0000084',
                'address' => 'Flat 3A, Silver Oak Apts, Zoo Road',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'state_id' => $stateAssam,
                'pincode' => '781024',
            ],
            [
                'name' => 'Rohit Deshmukh',
                'phone' => '9845998877',
                'email' => 'rohit.deshmukh@amazon.com',
                'pan' => 'BSDPN2233L',
                'parent_name' => 'Sanjay Deshmukh',
                'gender' => 'Male',
                'dob' => '1990-11-20',
                'occupation' => 'Senior Software Development Engineer',
                'employer' => 'Amazon Development Centre',
                'income' => 185000.00,
                'emergency_contact_name' => 'Pooja Deshmukh (Spouse)',
                'emergency_contact_phone' => '9845011224',
                'bank_name' => 'ICICI Bank',
                'account_number' => '000201589123',
                'ifsc_code' => 'ICIC0000002',
                'address' => '42, 4th Cross, Indiranagar',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560038',
            ],
            [
                'name' => 'Priya Sundaram',
                'phone' => '9900998877',
                'email' => 'priya.sundaram@google.com',
                'pan' => 'CSDPN3344M',
                'parent_name' => 'R. Sundaram',
                'gender' => 'Female',
                'dob' => '1994-03-18',
                'occupation' => 'Staff Product Specialist',
                'employer' => 'Google India',
                'income' => 195000.00,
                'emergency_contact_name' => 'R. Sundaram (Father)',
                'emergency_contact_phone' => '9900011225',
                'bank_name' => 'State Bank of India',
                'account_number' => '30981245678',
                'ifsc_code' => 'SBIN0000813',
                'address' => '15, 1st Main, Koramangala 4th Block',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560034',
            ],
            [
                'name' => 'Amit Patel',
                'phone' => '9880998877',
                'email' => 'amit.patel@microsoft.com',
                'pan' => 'DSDPN4455N',
                'parent_name' => 'Kirit Patel',
                'gender' => 'Male',
                'dob' => '1989-08-09',
                'occupation' => 'Principal Program Manager',
                'employer' => 'Microsoft India',
                'income' => 220000.00,
                'emergency_contact_name' => 'Kirit Patel (Father)',
                'emergency_contact_phone' => '9880011226',
                'bank_name' => 'Axis Bank',
                'account_number' => '912010045678912',
                'ifsc_code' => 'UTIB0000015',
                'address' => '88, Green Glen Layout, Bellandur',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560103',
            ],
            [
                'name' => 'Neha Joshi',
                'phone' => '9864887766',
                'email' => 'neha.joshi@airtel.in',
                'pan' => 'ESDPN5566P',
                'parent_name' => 'Pramod Joshi',
                'gender' => 'Female',
                'dob' => '1993-12-05',
                'occupation' => 'Regional Marketing Head',
                'employer' => 'Bharti Airtel Ltd',
                'income' => 110000.00,
                'emergency_contact_name' => 'Anil Joshi (Brother)',
                'emergency_contact_phone' => '9864011227',
                'bank_name' => 'HDFC Bank',
                'account_number' => '5010043891235',
                'ifsc_code' => 'HDFC0000084',
                'address' => 'Plot 12, G.S. Road, Dispur',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'state_id' => $stateAssam,
                'pincode' => '781005',
            ],
            [
                'name' => 'Varun Chawla',
                'phone' => '9845887766',
                'email' => 'varun.chawla@flipkart.com',
                'pan' => 'FSDPN6677Q',
                'parent_name' => 'Harish Chawla',
                'gender' => 'Male',
                'dob' => '1991-07-22',
                'occupation' => 'Associate Director - Supply Chain',
                'employer' => 'Flipkart Internet Pvt Ltd',
                'income' => 175000.00,
                'emergency_contact_name' => 'Harish Chawla (Father)',
                'emergency_contact_phone' => '9845011228',
                'bank_name' => 'Kotak Mahindra Bank',
                'account_number' => '4512789012',
                'ifsc_code' => 'KKBK0000422',
                'address' => '102, 14th Main, HSR Layout Sector 4',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560102',
            ],
            [
                'name' => 'Tanvi Gupta',
                'phone' => '9900887766',
                'email' => 'tanvi.gupta@swiggy.in',
                'pan' => 'GSDPN7788R',
                'parent_name' => 'Rajesh Gupta',
                'gender' => 'Female',
                'dob' => '1995-09-12',
                'occupation' => 'Senior UI/UX Designer',
                'employer' => 'Swiggy (Bundl Technologies)',
                'income' => 135000.00,
                'emergency_contact_name' => 'Sunita Gupta (Mother)',
                'emergency_contact_phone' => '9900011229',
                'bank_name' => 'HDFC Bank',
                'account_number' => '5010043891236',
                'ifsc_code' => 'HDFC0000140',
                'address' => '56, 17th Cross, HSR Layout Sector 7',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560102',
            ],
            [
                'name' => 'Arjun Menon',
                'phone' => '9880887766',
                'email' => 'arjun.menon@cisco.com',
                'pan' => 'HSDPN8899S',
                'parent_name' => 'K. V. Menon',
                'gender' => 'Male',
                'dob' => '1988-04-30',
                'occupation' => 'Technical Solutions Architect',
                'employer' => 'Cisco Systems India',
                'income' => 210000.00,
                'emergency_contact_name' => 'K. V. Menon (Father)',
                'emergency_contact_phone' => '9880011230',
                'bank_name' => 'Standard Chartered Bank',
                'account_number' => '42505678901',
                'ifsc_code' => 'SCBL0036001',
                'address' => 'Villa 7, Palm Meadows, Whitefield',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560066',
            ],
            [
                'name' => 'Divya Nair',
                'phone' => '9864776655',
                'email' => 'divya.nair@hdfcbank.com',
                'pan' => 'ISDPN9900T',
                'parent_name' => 'G. K. Nair',
                'gender' => 'Female',
                'dob' => '1992-01-15',
                'occupation' => 'Branch Operations Manager',
                'employer' => 'HDFC Bank Ltd',
                'income' => 98000.00,
                'emergency_contact_name' => 'G. K. Nair (Father)',
                'emergency_contact_phone' => '9864011231',
                'bank_name' => 'HDFC Bank',
                'account_number' => '5010043891237',
                'ifsc_code' => 'HDFC0000084',
                'address' => 'B-402, Brahmaputra Heights, Ulubari',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'state_id' => $stateAssam,
                'pincode' => '781007',
            ],
            [
                'name' => 'Karthik Rajan',
                'phone' => '9845776655',
                'email' => 'karthik.rajan@oracle.com',
                'pan' => 'JSDPN0011U',
                'parent_name' => 'S. Rajan',
                'gender' => 'Male',
                'dob' => '1990-06-18',
                'occupation' => 'Principal Database Administrator',
                'employer' => 'Oracle India Pvt Ltd',
                'income' => 165000.00,
                'emergency_contact_name' => 'Meera Rajan (Spouse)',
                'emergency_contact_phone' => '9845011232',
                'bank_name' => 'Citibank N.A.',
                'account_number' => '5489012345',
                'ifsc_code' => 'CITI0000004',
                'address' => 'Flat 204, Prestige Ozone, Whitefield',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560066',
            ],
            [
                'name' => 'Sandeep Hazarika',
                'phone' => '9900776655',
                'email' => 'sandeep.hazarika@oilindia.in',
                'pan' => 'KSDPN1122V',
                'parent_name' => 'Bhaben Hazarika',
                'gender' => 'Male',
                'dob' => '1987-10-10',
                'occupation' => 'Senior Executive Engineer',
                'employer' => 'Oil India Limited',
                'income' => 145000.00,
                'emergency_contact_name' => 'Bhaben Hazarika (Father)',
                'emergency_contact_phone' => '9900011233',
                'bank_name' => 'State Bank of India',
                'account_number' => '20194857612',
                'ifsc_code' => 'SBIN0000078',
                'address' => 'H.No 18, Bye-lane 3, Beltola',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'state_id' => $stateAssam,
                'pincode' => '781028',
            ],
            [
                'name' => 'Manash Phukan',
                'phone' => '9880776655',
                'email' => 'manash.phukan@ioc.in',
                'pan' => 'LSDPN2233W',
                'parent_name' => 'Pradip Phukan',
                'gender' => 'Male',
                'dob' => '1991-02-28',
                'occupation' => 'Deputy Manager (Operations)',
                'employer' => 'Indian Oil Corporation Ltd',
                'income' => 130000.00,
                'emergency_contact_name' => 'Pradip Phukan (Father)',
                'emergency_contact_phone' => '9880011234',
                'bank_name' => 'Punjab National Bank',
                'account_number' => '0145000100234567',
                'ifsc_code' => 'PUNB0014500',
                'address' => 'IOCL Colony, Noonmati',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'state_id' => $stateAssam,
                'pincode' => '781020',
            ],
            [
                'name' => 'Ritu Bora',
                'phone' => '9864665544',
                'email' => 'ritu.bora@sbi.co.in',
                'pan' => 'MSDPN3344X',
                'parent_name' => 'Nayan Bora',
                'gender' => 'Female',
                'dob' => '1994-07-04',
                'occupation' => 'Assistant General Manager',
                'employer' => 'State Bank of India',
                'income' => 115000.00,
                'emergency_contact_name' => 'Nayan Bora (Father)',
                'emergency_contact_phone' => '9864011235',
                'bank_name' => 'State Bank of India',
                'account_number' => '30198765432',
                'ifsc_code' => 'SBIN0000078',
                'address' => 'Flat 5B, Nilachal Enclave, Ganeshguri',
                'city' => 'Guwahati',
                'state' => 'Assam',
                'state_id' => $stateAssam,
                'pincode' => '781006',
            ],
            [
                'name' => 'Gaurav Singhania',
                'phone' => '9845665544',
                'email' => 'gaurav.singhania@deloitte.com',
                'pan' => 'NSDPN4455Y',
                'parent_name' => 'V. K. Singhania',
                'gender' => 'Male',
                'dob' => '1989-11-17',
                'occupation' => 'Director - Risk & Financial Advisory',
                'employer' => 'Deloitte Touche Tohmatsu India LLP',
                'income' => 240000.00,
                'emergency_contact_name' => 'V. K. Singhania (Father)',
                'emergency_contact_phone' => '9845011236',
                'bank_name' => 'HDFC Bank',
                'account_number' => '5010043891238',
                'ifsc_code' => 'HDFC0000140',
                'address' => 'Penthouse 12, Sobha Morzaria Grandeur, Bannerghatta Road',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560029',
            ],
            [
                'name' => 'Pooja Hegde',
                'phone' => '9900665544',
                'email' => 'pooja.hegde@kpmg.com',
                'pan' => 'OSDPN5566Z',
                'parent_name' => 'Ganesh Hegde',
                'gender' => 'Female',
                'dob' => '1993-08-25',
                'occupation' => 'Senior Manager - Tax & Regulatory',
                'employer' => 'KPMG India',
                'income' => 155000.00,
                'emergency_contact_name' => 'Ganesh Hegde (Father)',
                'emergency_contact_phone' => '9900011237',
                'bank_name' => 'Axis Bank',
                'account_number' => '912010045678913',
                'ifsc_code' => 'UTIB0000015',
                'address' => '23, 7th Main, 2nd Stage, Indiranagar',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'state_id' => $stateKarnataka,
                'pincode' => '560038',
            ],
        ];

        $tenants = [];
        foreach ($tenantData as $data) {
            /** @var Party $party */
            $party = Party::firstOrCreate(
                ['email' => $data['email']],
                [
                    'party_type' => 'individual',
                    'display_name' => $data['name'],
                    'phone' => $data['phone'],
                    'state_id' => $data['state_id'],
                ]
            );

            // Always ensure the display name and phone are in sync
            $party->update([
                'display_name' => $data['name'],
                'phone' => $data['phone'],
                'state_id' => $data['state_id'] ?? $party->state_id,
            ]);

            // 1. Tenant Profile
            TenantProfile::updateOrCreate(
                ['party_id' => $party->id],
                [
                    'emergency_contact_name' => $data['emergency_contact_name'],
                    'emergency_contact_phone' => $data['emergency_contact_phone'],
                    'occupation' => $data['occupation'],
                    'employer_name' => $data['employer'],
                    'monthly_income' => $data['income'],
                ]
            );

            // 2. Individual KYC Identity
            PartyIndividual::updateOrCreate(
                ['party_id' => $party->id],
                [
                    'name' => $data['name'],
                    'parent_name' => $data['parent_name'],
                    'gender' => $data['gender'],
                    'date_of_birth' => $data['dob'],
                    'pan_number' => $data['pan'],
                    'aadhaar_number' => '88' . str_pad((string) abs(crc32($data['pan'])), 10, '0', STR_PAD_LEFT),
                ]
            );

            // 3. Bank Account
            PartyBankAccount::updateOrCreate(
                [
                    'party_id' => $party->id,
                    'account_number' => $data['account_number'],
                ],
                [
                    'bank_name' => $data['bank_name'],
                    'beneficiary_name' => $data['name'],
                    'ifsc_code' => $data['ifsc_code'],
                    'is_primary' => true,
                    'is_verified' => true,
                ]
            );

            // 4. Address Details
            PartyAddress::updateOrCreate(
                [
                    'party_id' => $party->id,
                    'type' => 'permanent',
                ],
                [
                    'address_line_1' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'pincode' => $data['pincode'],
                    'country' => 'India',
                    'is_primary' => true,
                ]
            );

            // 5. Accounting Provisioning (Contact + AR subledger + AP subledger + Security Deposit liability)
            try {
                $provisioningService->ensurePartyAccountingReady($party);
            } catch (\Throwable $e) {
                $this->command?->warn("Could not provision accounting for {$party->display_name}: {$e->getMessage()}");
            }

            $tenants[] = $party;
        }

        return $tenants;
    }
}
