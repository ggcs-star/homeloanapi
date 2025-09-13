<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChildEducationController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'current_cost'    => 'required|numeric|min:1',
            'current_age'     => 'required|integer|min:0',
            'target_age'      => 'required|integer|min:1',
            'inflation_rate'  => 'required|numeric|min:0',
            'return_rate'     => 'required|numeric|min:0',
            'current_savings' => 'nullable|numeric|min:0',
        ]);

        $currentCost    = $request->current_cost;
        $currentAge     = $request->current_age;
        $targetAge      = $request->target_age;
        $inflationRate  = $request->inflation_rate / 100.0;  // 10% -> 0.1
        $returnRate     = $request->return_rate / 100.0;     // 12% -> 0.12
        $currentSavings = $request->current_savings ?? 0.0;

        $yearsLeft = max(0, $targetAge - $currentAge);
        $months    = $yearsLeft * 12;

        // Step 1: Future cost (inflation compounded annually)
        $futureCost = $currentCost * pow(1 + $inflationRate, $yearsLeft);

        // Step 2: Required ANNUAL savings  (standard annual annuity formula)
        // A = FV * r / ((1+r)^n - 1)
        $annualFactor = pow(1 + $returnRate, $yearsLeft) - 1.0;
        if ($annualFactor > 0) {
            $requiredAnnual = ($futureCost - $currentSavings) * $returnRate / $annualFactor;
        } else {
            // fallback if returnRate == 0
            $requiredAnnual = ($futureCost - $currentSavings) / max(1, $yearsLeft);
        }

        // Step 3: Required MONTHLY savings using effective monthly rate
        // monthly effective rate = (1 + r)^(1/12) - 1
        // M = FV * m / ((1+m)^N - 1)
        if ($yearsLeft > 0) {
            $monthlyEff = pow(1 + $returnRate, 1/12.0) - 1.0;
            $N = $months;
            $monthlyFactor = pow(1 + $monthlyEff, $N) - 1.0;
            if ($monthlyFactor > 0) {
                $requiredMonthly = ($futureCost - $currentSavings) * $monthlyEff / $monthlyFactor;
            } else {
                $requiredMonthly = ($futureCost - $currentSavings) / max(1, $N);
            }
        } else {
            $requiredMonthly = 0.0;
        }

        return response()->json([
            'yearsLeft'        => $yearsLeft,
            'months'           => $months,
            'inflationRate'    => $inflationRate,
            'returnRate'       => $returnRate,
            'futureCost'       => round($futureCost, 2),
            'requiredAnnual'   => round($requiredAnnual, 2),
            'requiredMonthly'  => round($requiredMonthly, 2),
            'note'             => 'annual computed with standard annual annuity; monthly uses effective monthly rate'
        ]);
    }
}
