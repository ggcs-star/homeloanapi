<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CareerBreakCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'pre_break_salary' => 'required|numeric',
            'annual_salary_growth' => 'required|numeric',
            'career_break_years' => 'required|integer|min:0',
            'alternative_income' => 'required|numeric',
            'expected_salary_on_return' => 'required|numeric',
            'post_break_growth' => 'required|numeric',
            'remaining_career_years' => 'required|integer|min:0',
            'investment_return_rate' => 'required|numeric',
        ]);

        $p = (float) $validated['pre_break_salary'];
        $g = (float) $validated['annual_salary_growth'] / 100.0;
        $t = (int) $validated['career_break_years'];
        $alt = (float) $validated['alternative_income'];
        $ret = (float) $validated['expected_salary_on_return'];
        $pg = (float) $validated['post_break_growth'] / 100.0;
        $remaining = (int) $validated['remaining_career_years'];
        $r = (float) $validated['investment_return_rate'] / 100.0;

        // Projected earnings during break
        $projectedDuringBreak = 0.0;
        for ($i = 0; $i < $t; $i++) {
            $projectedDuringBreak += $p * pow(1.0 + $g, $i);
        }

        // Foregone earnings during break
        $actualDuringBreak = $alt * $t;
        $foregoneDuringBreak = $projectedDuringBreak - $actualDuringBreak;

        // Salary at return if no break
        $salaryNoBreakAtReturn = $p * pow(1.0 + $g, $t);

        // Salary gap on return
        $salaryGapOnReturn = $salaryNoBreakAtReturn - $ret;

        // Career impact
        if ($pg == 0.0) {
            $careerImpact = $salaryGapOnReturn * $remaining;
        } else {
            $careerImpact = $salaryGapOnReturn * (pow(1.0 + $pg, $remaining) - 1.0) / $pg;
        }

        // Opportunity cost (future value)
        if ($r == 0.0) {
            $opportunityFV = $foregoneDuringBreak * $remaining;
        } else {
            $opportunityFV = $foregoneDuringBreak * (pow(1.0 + $r, $remaining) - 1.0) / $r;
        }

        // Total financial impact = foregone + careerImpact (same as app screenshot)
        $totalImpact = $foregoneDuringBreak + $careerImpact;

        return response()->json([
            'foregone_during_break' => round($foregoneDuringBreak),
            'career_impact' => round($careerImpact),
            'opportunity_cost' => round($opportunityFV),
            'total_financial_impact' => round($totalImpact),
        ]);
    }
}
