<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InterestRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run()
{
    \DB::table('interest_rates')->insert([
        ['type' => 'loan', 'rate' => 9.0],
        ['type' => 'fd', 'rate' => 7.5],
        ['type' => 'sip', 'rate' => 12.0],
        ['type' => 'swp', 'rate' => 10.0],
    ]);
}
}