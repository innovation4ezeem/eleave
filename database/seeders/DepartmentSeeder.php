<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Human Resources', 'Finance', 'Information Technology', 'Operations'] as $name) {
            Department::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
