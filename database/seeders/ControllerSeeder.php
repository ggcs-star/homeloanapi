<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ControllerSeeder extends Seeder
{
    public function run()
    {
        // Yaha controller ke naam add karo
        $controllers = [
            'UserController',
            'LoanController',
            'EmiController',
            'InterestRateController',
            // Add more as per your project
        ];

        foreach ($controllers as $controller) {
            DB::table('controllers')->insert([
                'name' => $controller,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
