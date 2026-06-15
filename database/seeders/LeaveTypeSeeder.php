<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Annual Leave', 'code' => 'annual', 'default_annual_quota' => 14, 'affects_balance' => true, 'requires_document' => false],
            ['name' => 'Carry Forward Leave', 'code' => 'carry_forward', 'default_annual_quota' => 0, 'affects_balance' => true, 'requires_document' => false],
            ['name' => 'Additional Leave', 'code' => 'additional', 'default_annual_quota' => 0, 'affects_balance' => true, 'requires_document' => false],
            ['name' => 'Medical Leave', 'code' => 'medical', 'default_annual_quota' => 14, 'affects_balance' => true, 'requires_document' => true],
            ['name' => 'Compassionate Leave', 'code' => 'compassionate', 'default_annual_quota' => 3, 'affects_balance' => true, 'requires_document' => false],
            ['name' => 'Maternity Leave', 'code' => 'maternity', 'default_annual_quota' => 60, 'affects_balance' => true, 'requires_document' => true],
            ['name' => 'Paternity Leave', 'code' => 'paternity', 'default_annual_quota' => 7, 'affects_balance' => true, 'requires_document' => false],
            ['name' => 'Replacement Leave', 'code' => 'replacement', 'default_annual_quota' => 0, 'affects_balance' => true, 'requires_document' => false],
            ['name' => 'Reward Leave', 'code' => 'reward', 'default_annual_quota' => 0, 'affects_balance' => true, 'requires_document' => false],
            ['name' => 'Unpaid Leave', 'code' => 'unpaid', 'default_annual_quota' => 0, 'affects_balance' => false, 'requires_document' => false],
            ['name' => 'Work From Home', 'code' => 'wfh', 'default_annual_quota' => 0, 'affects_balance' => true, 'requires_document' => false],
        ];

        foreach ($types as $index => $type) {
            LeaveType::updateOrCreate(
                ['code' => $type['code']],
                $type + ['is_active' => true, 'sort_order' => $index + 1],
            );
        }
    }
}
