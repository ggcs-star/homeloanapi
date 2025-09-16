<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rate;

class RateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            [
                'calculator' => 'commision-calculator',
                'settings' => [
                    'inflation_rate' => 6,
                    'loan_rate' => 8,
                ],
            ],
          
        ];

        Rate::insert($rates);
    }
}
