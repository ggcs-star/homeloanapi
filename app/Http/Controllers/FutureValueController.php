<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FutureValueController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'present_balance' => 'required|numeric|min:1',
            'bank_rate' => 'required|numeric|min:0',
            'inflation_rate' => 'required|numeric|min:0',
            'years' => 'required|integer|min:1',
        ]);

        $presentBalance = $request->present_balance;
        $bankRate = $request->bank_rate / 100;       // % to decimal
        $inflationRate = $request->inflation_rate / 100;
        $years = $request->years;

        // 1. Future Value with Bank Interest
        $futureValueNominal = $presentBalance * pow((1 + $bankRate), $years);

        // 2. Future Cost needed to match today’s purchasing power
        $futureCostToMatchToday = $presentBalance * pow((1 + $inflationRate), $years);

        // 3. Real Value of Bank Balance after Inflation
        $futureValueReal = $futureValueNominal / pow((1 + $inflationRate), $years);

        return response()->json([
            'present_balance' => $presentBalance,
            'bank_rate_percent' => $request->bank_rate,
            'inflation_rate_percent' => $request->inflation_rate,
            'years' => $years,
            'future_value_nominal' => round($futureValueNominal, 2),
            'future_cost_to_match_today' => round($futureCostToMatchToday, 2),
            'future_value_inflation_adjusted' => round($futureValueReal, 2),
        ]);
    }
}
