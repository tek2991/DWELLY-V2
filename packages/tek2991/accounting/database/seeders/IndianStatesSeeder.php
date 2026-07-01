<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Tek2991\Accounting\Models\State;

class IndianStatesSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['code' => 'JK', 'name' => 'Jammu & Kashmir', 'gst_state_code' => '01', 'is_union_territory' => true],
            ['code' => 'HP', 'name' => 'Himachal Pradesh', 'gst_state_code' => '02', 'is_union_territory' => false],
            ['code' => 'PB', 'name' => 'Punjab', 'gst_state_code' => '03', 'is_union_territory' => false],
            ['code' => 'CH', 'name' => 'Chandigarh', 'gst_state_code' => '04', 'is_union_territory' => true],
            ['code' => 'UK', 'name' => 'Uttarakhand', 'gst_state_code' => '05', 'is_union_territory' => false],
            ['code' => 'HR', 'name' => 'Haryana', 'gst_state_code' => '06', 'is_union_territory' => false],
            ['code' => 'DL', 'name' => 'Delhi', 'gst_state_code' => '07', 'is_union_territory' => true],
            ['code' => 'RJ', 'name' => 'Rajasthan', 'gst_state_code' => '08', 'is_union_territory' => false],
            ['code' => 'UP', 'name' => 'Uttar Pradesh', 'gst_state_code' => '09', 'is_union_territory' => false],
            ['code' => 'BR', 'name' => 'Bihar', 'gst_state_code' => '10', 'is_union_territory' => false],
            ['code' => 'SK', 'name' => 'Sikkim', 'gst_state_code' => '11', 'is_union_territory' => false],
            ['code' => 'AR', 'name' => 'Arunachal Pradesh', 'gst_state_code' => '12', 'is_union_territory' => false],
            ['code' => 'NL', 'name' => 'Nagaland', 'gst_state_code' => '13', 'is_union_territory' => false],
            ['code' => 'MN', 'name' => 'Manipur', 'gst_state_code' => '14', 'is_union_territory' => false],
            ['code' => 'MZ', 'name' => 'Mizoram', 'gst_state_code' => '15', 'is_union_territory' => false],
            ['code' => 'TR', 'name' => 'Tripura', 'gst_state_code' => '16', 'is_union_territory' => false],
            ['code' => 'ML', 'name' => 'Meghalaya', 'gst_state_code' => '17', 'is_union_territory' => false],
            ['code' => 'AS', 'name' => 'Assam', 'gst_state_code' => '18', 'is_union_territory' => false],
            ['code' => 'WB', 'name' => 'West Bengal', 'gst_state_code' => '19', 'is_union_territory' => false],
            ['code' => 'JH', 'name' => 'Jharkhand', 'gst_state_code' => '20', 'is_union_territory' => false],
            ['code' => 'OD', 'name' => 'Odisha', 'gst_state_code' => '21', 'is_union_territory' => false],
            ['code' => 'CG', 'name' => 'Chhattisgarh', 'gst_state_code' => '22', 'is_union_territory' => false],
            ['code' => 'MP', 'name' => 'Madhya Pradesh', 'gst_state_code' => '23', 'is_union_territory' => false],
            ['code' => 'GJ', 'name' => 'Gujarat', 'gst_state_code' => '24', 'is_union_territory' => false],
            ['code' => 'DD', 'name' => 'Dadra & Nagar Haveli and Daman & Diu', 'gst_state_code' => '25', 'is_union_territory' => true],
            ['code' => 'DDL', 'name' => 'Dadra & Nagar Haveli and Daman & Diu (legacy)', 'gst_state_code' => '26', 'is_union_territory' => true],
            ['code' => 'MH', 'name' => 'Maharashtra', 'gst_state_code' => '27', 'is_union_territory' => false],
            ['code' => 'AP', 'name' => 'Andhra Pradesh', 'gst_state_code' => '28', 'is_union_territory' => false],
            ['code' => 'KA', 'name' => 'Karnataka', 'gst_state_code' => '29', 'is_union_territory' => false],
            ['code' => 'GA', 'name' => 'Goa', 'gst_state_code' => '30', 'is_union_territory' => false],
            ['code' => 'LD', 'name' => 'Lakshadweep', 'gst_state_code' => '31', 'is_union_territory' => true],
            ['code' => 'KL', 'name' => 'Kerala', 'gst_state_code' => '32', 'is_union_territory' => false],
            ['code' => 'TN', 'name' => 'Tamil Nadu', 'gst_state_code' => '33', 'is_union_territory' => false],
            ['code' => 'PY', 'name' => 'Puducherry', 'gst_state_code' => '34', 'is_union_territory' => true],
            ['code' => 'AN', 'name' => 'Andaman & Nicobar Islands', 'gst_state_code' => '35', 'is_union_territory' => true],
            ['code' => 'TS', 'name' => 'Telangana', 'gst_state_code' => '36', 'is_union_territory' => false],
            ['code' => 'APN', 'name' => 'Andhra Pradesh (new)', 'gst_state_code' => '37', 'is_union_territory' => false],
            ['code' => 'LA', 'name' => 'Ladakh', 'gst_state_code' => '38', 'is_union_territory' => true],
        ];

        foreach ($states as $state) {
            State::updateOrCreate(
                ['country_id' => 'IN', 'gst_state_code' => $state['gst_state_code']],
                [
                    'name' => $state['name'],
                    'code' => $state['code'],
                    'is_union_territory' => $state['is_union_territory'],
                ]
            );
        }
    }
}
