<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

class MisCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'lump_sum' => 'required|numeric|min:0',
            // annual_rate ab required nahi hai, kyunki DB से bhi aayega
            'annual_rate' => 'nullable|numeric|min:0'
        ]);

        $P = (float)$request->lump_sum;
        $t = 5; // MIS term fixed = 5 years

        // ✅ DB से fetch करो (commision-calculator)
        $rateData = Rate::where('calculator', 'commision-calculator')->first();
        $adminRate = $rateData->settings['loan_rate'] ?? null; // MIS ke liye hum isko use karenge

        // ✅ Annual Rate (User → DB) (no fallback)
        if ($request->filled('annual_rate')) {
            $r = (float)$request->input('annual_rate') / 100.0;
            $rateSource = 'user_input';
        } elseif ($adminRate !== null) {
            $r = (float)$adminRate / 100.0;
            $rateSource = 'db_admin';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Annual rate not provided by user or DB',
                'error' => 'missing_annual_rate',
                'data' => null
            ], 422);
        }

        // ✅ Monthly income
        $monthlyIncome = $P * $r / 12;

        // ✅ Total interest earned in 5 years
        $totalInterest = $monthlyIncome * 12 * $t;

        // ✅ Principal (returned after 5 years)
        $maturity = $P;

        // ✅ Total return = Principal + Interest
        $totalReturn = $P + $totalInterest;

        // ✅ Year-wise breakdown (5 years fixed)
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
            'status' => 'success',
            'message' => 'MIS calculation completed successfully',
            'error' => null,
            'data' => [
                'lump_sum' => round($P, 2),
                'annual_rate_percent' => $r * 100,
                'rate_source' => $rateSource,
                'term_years' => $t,
                'monthly_income' => round($monthlyIncome, 2),
                'total_interest' => round($totalInterest, 2),
                'maturity_amount' => round($maturity, 2),
                'total_return' => round($totalReturn, 2),
                'year_wise_breakdown' => $yearWise
            ]
        ]);
    }
}
