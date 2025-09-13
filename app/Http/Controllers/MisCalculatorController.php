<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MisCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'lump_sum' => 'required|numeric|min:0',
            'annual_rate' => 'required|numeric|min:0'
        ]);

        $P = (float)$request->lump_sum;
        $r = (float)$request->annual_rate / 100.0;
        $t = 5; // term always fixed = 5 years

        // Monthly income
        $monthlyIncome = $P * $r / 12;

        // Total interest earned in 5 years
        $totalInterest = $monthlyIncome * 12 * $t;

        // Principal (returned after 5 years)
        $maturity = $P;

        // Total return = Principal + Interest
        $totalReturn = $P + $totalInterest;

        // Year-wise breakdown (5 years fixed)
        $yearWise = [];
        $cumulative = 0;
        for ($year = 1; $year <= $t; $year++) {
            $yearInterest = $monthlyIncome * 12;
            $cumulative += $yearInterest;
            $yearWise[] = [
                'year' => $year,
                'interest_this_year' => round($yearInterest, 2),
                'cumulative_interest' => round($cumulative, 2),
                'principal' => $P,
                'total_payout_so_far' => round($P + $cumulative, 2)
            ];
        }

        return response()->json([
            'lump_sum' => round($P, 2),
            'annual_rate_percent' => $request->annual_rate,
            'term_years' => $t,
            'monthly_income' => round($monthlyIncome, 2),
            'total_interest' => round($totalInterest, 2),
            'maturity_amount' => round($maturity, 2),
            'total_return' => round($totalReturn, 2),
         
        ]);
    }
}
