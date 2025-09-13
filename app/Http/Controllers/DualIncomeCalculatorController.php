<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
            'investment_return' => 'required|numeric',
        ]);

        // Inputs
        $primary = (float) $validated['primary_income'];
        $secondary = (float) $validated['secondary_income'];
        $taxRate = (float) $validated['tax_rate_secondary'] / 100.0;

        // Individual extra-costs (dual-income only)
        $childcare = (float) $validated['childcare'];
        $commuting = (float) $validated['commuting'];
        $meals = (float) $validated['meals'];
        $wardrobe = (float) $validated['wardrobe'];
        $housekeeping = (float) $validated['housekeeping'];
        $extra_home = (float) $validated['extra_home'];
        $time_opportunity_cost = (float) $validated['time_opportunity_cost'];

        // Aggregate
        $total_extra_costs = $childcare + $commuting + $meals + $wardrobe + $housekeeping + $extra_home + $time_opportunity_cost;

        $years = (int) $validated['analysis_years'];
        $r = (float) $validated['investment_return'] / 100.0;

        // Dual-income scenario
        $secondary_net = $secondary * (1.0 - $taxRate);          // net after tax
        $dual_effective = $primary + $secondary_net - $total_extra_costs;

        // Single-income scenario: partner not working -> you save the extra costs
        // Effective single-income take-home = primary + total_extra_costs
        $single_effective = $primary + $total_extra_costs;

        // Difference (Single - Dual)
        $annual_difference = $single_effective - $dual_effective;

        // Future value of investing that annual difference for `years` at rate r (annuity future value)
        if ($r == 0.0) {
            $future_value = $annual_difference * $years;
        } else {
            $future_value = $annual_difference * (pow(1.0 + $r, $years) - 1.0) / $r;
        }

        // Round to nearest rupee
        $round = function ($val) {
            return (int) round($val);
        };

        return response()->json([
            'secondary_net_income' => $round($secondary_net),
            'total_extra_costs' => $round($total_extra_costs),
            'dual_income_effective_annual' => $round($dual_effective),
            'single_income_effective_annual' => $round($single_effective),
            'annual_difference_single_minus_dual' => $round($annual_difference),
            'future_value_of_annual_difference' => $round($future_value)
        ]);
    }
}
