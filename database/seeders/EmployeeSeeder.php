<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('hu_HU');
        
        $companies = Company::query()->pluck('id');
        
        if ($companies->isEmpty()) {
            $this->command?->warn('No companies found. Skipping EmployeeSeeder.');
            return;
        }
        
        foreach ($companies as $companyId) {
            // cégenként 10–30 dolgozó
            $count = rand(10, 30);
            
            for ($i = 0; $i < $count; $i++) {
                $employeeNumber = sprintf(
                    'EMP-%d-%04d',
                    $companyId,
                    $i + 1
                );
                
                Employee::firstOrCreate(
                    [
                        'company_id' => $companyId,
                        'employee_number' => $employeeNumber,
                    ],
                    [
                        'name' => $faker->name(),
                        'email' => $faker->unique()->safeEmail(),
                        'phone' => $faker->phoneNumber(),
                        'position' => $faker->randomElement([
                            'Frontend Developer',
                            'Backend Developer',
                            'Project Manager',
                            'HR Specialist',
                            'QA Engineer',
                            'DevOps Engineer',
                            'Support Agent',
                        ]),
                        'is_active' => $faker->boolean(85),
                    ]
                );
            }
        }
        
        $this->command?->info('Employees seeded successfully.');
        
        /*
        collect([
            [
                'company_id' => '',
                'employee_number' => '',
                'name' => '',
                'email' => '',
                'phone' => '',
                'position' => '',
                'is_active' => '',
            ]
        ])->each(
            fn(array $employee) => Employee::firstOrCreate($employee)
        );
        */
    }
}