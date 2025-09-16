<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class DualIncomeCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'primary_income' => 'required|numeric',
            'secondary_income' => 'required|numeric',
            'tax_rate_secondary' => 'required|numeric',
            'childcare' => 'required|numeric',
            'commuting' => 'required|numeric',
            'meals' => 'required|numeric',
            'wardrobe' => 'required|numeric',
            'housekeeping' => 'required|numeric',
            'extra_home' => 'required|numeric',
            'time_opportunity_cost' => 'required|numeric',
            'analysis_years' => 'required|integer|min:1',
            'investment_return' => 'nullable|numeric|min:0', // optional now
        ]);

        // ✅ DB fetch
        $rateData = Rate::where('calculator', 'commision-calculator')->first();

        // Investment return (user → admin → fallback)
        if ($request->filled('investment_return')) {
            $r = (float) $validated['investment_return'] / 100.0;
            $returnSource = 'user';
        } else {
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $r = (float) $rateData->settings['loan_rate'] / 100.0;
            } else {
                $r = 0.10; // fallback 10%
            }
            $returnSource = 'admin';
        }

        // Inputs
        $primary = (float) $validated['primary_income'];
        $secondary = (float) $validated['secondary_income'];
        $taxRate = (float) $validated['tax_rate_secondary'] / 100.0;

        // Extra costs
        $childcare = (float) $validated['childcare'];
        $commuting = (float) $validated['commuting'];
        $meals = (float) $validated['meals'];
        $wardrobe = (float) $validated['wardrobe'];
        $housekeeping = (float) $validated['housekeeping'];
        $extra_home = (float) $validated['extra_home'];
        $time_opportunity_cost = (float) $validated['time_opportunity_cost'];

        $total_extra_costs = $childcare + $commuting + $meals + $wardrobe + $housekeeping + $extra_home + $time_opportunity_cost;

        $years = (int) $validated['analysis_years'];

        // Dual-income scenario
        $secondary_net = $secondary * (1.0 - $taxRate);
        $dual_effective = $primary + $secondary_net - $total_extra_costs;

        // Single-income scenario
        $single_effective = $primary + $total_extra_costs;

        // Difference
        $annual_difference = $single_effective - $dual_effective;

        // Future value
        if ($r == 0.0) {
            $future_value = $annual_difference * $years;
        } else {
            $future_value = $annual_difference * (pow(1.0 + $r, $years) - 1.0) / $r;
        }

        $round = fn($val) => (int) round($val);

        return response()->json([
            'secondary_net_income' => $round($secondary_net),
            'total_extra_costs' => $round($total_extra_costs),
            'dual_income_effective_annual' => $round($dual_effective),
            'single_income_effective_annual' => $round($single_effective),
            'annual_difference_single_minus_dual' => $round($annual_difference),
            'future_value_of_annual_difference' => $round($future_value),
            'rates_used' => [
                'investment_return_percent' => round($r * 100, 2),
                'investment_return_source'  => $returnSource
            ]
        ]);
    }
}
