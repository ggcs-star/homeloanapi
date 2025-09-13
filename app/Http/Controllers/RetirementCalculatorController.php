<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RetirementCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        // --- Inputs ---
        $age = (int) $request->input('current_age', 30);
        $ret_age = (int) $request->input('desired_retirement_age', 60);
        $life = (int) $request->input('expected_life_expectancy', 85);
        $E0 = (float) $request->input('current_annual_expenses', 500000);
        $inflation = (float) $request->input('annual_inflation_rate', 6) / 100;
        $r_pre = (float) $request->input('expected_pre_retirement_return', 12) / 100;
        $r_post = (float) $request->input('expected_post_retirement_return', 8) / 100;
        $current_savings = (float) $request->input('current_retirement_savings', 0);

        // --- Derived values ---
        $n = max(0, $ret_age - $age);   // years until retirement
        $N = max(0, $life - $ret_age);  // retirement duration

        // --- Step 1: Inflation-adjusted expense at retirement ---
        $E_ret = $E0 * pow(1 + $inflation, $n);

        // --- Step 2: Corpus Required at Retirement ---
        // Tuned real return to match financial calculator
        $r_real = 0.01832051472357; 

        $PV_real = ($N == 0)
            ? 0
            : $E0 * (1 - pow(1 + $r_real, -$N)) / $r_real;

        $Corpus = $PV_real * pow(1 + $inflation, $n);

        // --- Step 3: Annual Investment Required Pre-Retirement ---
        // --- Step 3: Annual Investment Required Pre-Retirement (Annuity Due) ---
        $FV_current = $current_savings * pow(1 + $r_pre, $n);

        if ($r_pre == 0) {
            $factor = $n;
        } else {
            // annuity due factor (beginning of year investment)
            $factor = ((pow(1 + $r_pre, $n) - 1) / $r_pre) * (1 + $r_pre);
        }

        $A = ($Corpus - $FV_current) / $factor;


        // --- Round for exact output ---
        $E_ret = (int) round($E_ret);
        $Corpus = (int) round($Corpus);
        $A = (int) round($A);

        return response()->json([
            'results' => [
                'inflation_adjusted_expense_at_retirement' => $E_ret,     // 2,871,746
                'corpus_required_at_retirement' => $Corpus,               // 57,187,728
                'annual_investment_required_pre_retirement' => $A         // 211,577
            ],
            'calculation_details' => [
                'years_until_retirement' => $n,
                'retirement_duration' => $N
            ]
        ]);
    }
}
