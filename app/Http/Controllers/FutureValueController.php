<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class FutureValueController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'present_balance' => 'required|numeric|min:1',
            'bank_rate' => 'nullable|numeric|min:0',      // ✅ user input optional now
            'inflation_rate' => 'nullable|numeric|min:0', // ✅ user input optional now
            'years' => 'required|integer|min:1',
        ]);

        $presentBalance = (float) $request->present_balance;
        $years = (int) $request->years;

        // ✅ Fetch DB record
        $rateData = Rate::where('calculator', 'commision-calculator')->first();

        // --- Bank Rate ---
        if ($request->filled('bank_rate')) {
            $bankRate = (float) $request->input('bank_rate');
            $bankSource = 'user';
        } elseif ($rateData && isset($rateData->settings['loan_rate'])) {
            $bankRate = (float) $rateData->settings['loan_rate'];
            $bankSource = 'admin';
        } else {
            $bankRate = 8.0; // fallback
            $bankSource = 'system_default';
        }

        // --- Inflation Rate ---
        if ($request->filled('inflation_rate')) {
            $inflationRate = (float) $request->input('inflation_rate');
            $inflationSource = 'user';
        } elseif ($rateData && isset($rateData->settings['inflation_rate'])) {
            $inflationRate = (float) $rateData->settings['inflation_rate'];
            $inflationSource = 'admin';
        } else {
            $inflationRate = 6.0; // fallback
            $inflationSource = 'system_default';
        }

        // Convert to decimals
        $bankRateDecimal = $bankRate / 100.0;
        $inflationRateDecimal = $inflationRate / 100.0;

        // 1. Future Value with Bank Interest
        $futureValueNominal = $presentBalance * pow((1 + $bankRateDecimal), $years);

        // 2. Future Cost needed to match today’s purchasing power
        $futureCostToMatchToday = $presentBalance * pow((1 + $inflationRateDecimal), $years);

        // 3. Real Value of Bank Balance after Inflation
        $futureValueReal = $futureValueNominal / pow((1 + $inflationRateDecimal), $years);

        return response()->json([
            'present_balance' => $presentBalance,
            'years' => $years,
            'future_value_nominal' => round($futureValueNominal, 2),
            'future_cost_to_match_today' => round($futureCostToMatchToday, 2),
            'future_value_inflation_adjusted' => round($futureValueReal, 2),
            'rates_used' => [
                'bank_rate_percent' => $bankRate,
                'bank_rate_source' => $bankSource,
                'inflation_rate_percent' => $inflationRate,
                'inflation_rate_source' => $inflationSource,
            ]
        ]);
    }
}
