<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BasicLoan;

class BasicLoanSeeder extends Seeder
{
    public function run(): void
    {
DB::table('interest_rates')->insert([
    'name' => 'basic_loan',
    'rate' => 10,
    'created_at' => now(),
    'updated_at' => now(),
]);


        foreach ($sampleLoans as $loan) {
            BasicLoan::create($loan);
        }
    }
}
