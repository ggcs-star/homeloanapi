<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class ChildEducationController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'current_cost'    => 'required|numeric|min:1',
            'current_age'     => 'required|integer|min:0',
            'target_age'      => 'required|integer|min:1',
            'inflation_rate'  => 'nullable|numeric|min:0',
            'return_rate'     => 'nullable|numeric|min:0',
            'current_savings' => 'nullable|numeric|min:0',
        ]);

        // ✅ Inflation Rate (user → admin → fallback)
        if ($request->filled('inflation_rate')) {
            $inflationRate = $request->inflation_rate / 100.0;
            $inflationSource = 'user';
        } else {
            $rateData = Rate::where('calculator', 'commision-calculator')->first();
            if ($rateData && isset($rateData->settings['inflation_rate'])) {
                $inflationRate = $rateData->settings['inflation_rate'] / 100.0;
            } else {
                $inflationRate = 0.10; // fallback 10%
            }
            $inflationSource = 'admin';
        }

        // ✅ Return Rate (user → admin → fallback)
        if ($request->filled('return_rate')) {
            $returnRate = $request->return_rate / 100.0;
            $returnSource = 'user';
        } else {
            $rateData = Rate::where('calculator', 'commision-calculator')->first();
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $returnRate = $rateData->settings['loan_rate'] / 100.0;
            } else {
                $returnRate = 0.10; // fallback 10%
            }
            $returnSource = 'admin';
        }

        // Inputs
        $currentCost    = $request->current_cost;
        $currentAge     = $request->current_age;
        $targetAge      = $request->target_age;
        $currentSavings = $request->current_savings ?? 0.0;

        $yearsLeft = max(0, $targetAge - $currentAge);
        $months    = $yearsLeft * 12;

        // Step 1: Future cost (inflation compounded annually)
        $futureCost = $currentCost * pow(1 + $inflationRate, $yearsLeft);

        // Step 2: Required ANNUAL savings
        $annualFactor = pow(1 + $returnRate, $yearsLeft) - 1.0;
        if ($annualFactor > 0) {
            $requiredAnnual = ($futureCost - $currentSavings) * $returnRate / $annualFactor;
        } else {
            $requiredAnnual = ($futureCost - $currentSavings) / max(1, $yearsLeft);
        }

        // Step 3: Required MONTHLY savings
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
            'inflationRate_percent' => round($inflationRate * 100, 2),
            'inflationRate_source'  => $inflationSource,
            'returnRate_percent'    => round($returnRate * 100, 2),
            'returnRate_source'     => $returnSource,
            'futureCost'       => round($futureCost, 2),
            'requiredAnnual'   => round($requiredAnnual, 2),
            'requiredMonthly'  => round($requiredMonthly, 2),
            'note'             => 'annual computed with standard annual annuity; monthly uses effective monthly rate'
        ]);
    }
}
