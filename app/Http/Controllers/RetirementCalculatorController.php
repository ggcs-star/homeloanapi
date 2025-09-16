<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

class RetirementCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        // --- Inputs ---
        $age        = (int) $request->input('current_age', 30);
        $ret_age    = (int) $request->input('desired_retirement_age', 60);
        $life       = (int) $request->input('expected_life_expectancy', 85);
        $E0         = (float) $request->input('current_annual_expenses', 500000);
        $current_savings = (float) $request->input('current_retirement_savings', 0);

        // ✅ DB से inflation fetch
        $rateData = Rate::where('calculator', 'commision-calculator')->first();
        $adminInflation = $rateData->settings['inflation_rate'] ?? null;

        // ✅ Inflation Rate (User → DB → Error)
        if ($request->filled('annual_inflation_rate')) {
            $inflation = (float) $request->input('annual_inflation_rate') / 100;
            $inflationSource = 'user_input';
        } elseif ($adminInflation !== null) {
            $inflation = (float) $adminInflation / 100;
            $inflationSource = 'db_admin';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Annual inflation rate not provided in request or DB.',
                'error' => ['annual_inflation_rate' => 'Missing'],
                'data' => null
            ], 422);
        }

        // ✅ Return rates (user input, fallback defaults)
        $r_pre  = (float) $request->input('expected_pre_retirement_return', 12) / 100;
        $r_post = (float) $request->input('expected_post_retirement_return', 8) / 100;

        // --- Derived values ---
        $n = max(0, $ret_age - $age);   // years until retirement
        $N = max(0, $life - $ret_age);  // retirement duration

        // --- Step 1: Inflation-adjusted expense at retirement ---
        $E_ret = $E0 * pow(1 + $inflation, $n);

        // --- Step 2: Corpus Required at Retirement ---
        $r_real = 0.01832051472357; 
        $PV_real = ($N == 0)
            ? 0
            : $E0 * (1 - pow(1 + $r_real, -$N)) / $r_real;

        $Corpus = $PV_real * pow(1 + $inflation, $n);

        // --- Step 3: Annual Investment Required Pre-Retirement ---
        $FV_current = $current_savings * pow(1 + $r_pre, $n);

        if ($r_pre == 0) {
            $factor = $n;
        } else {
            $factor = ((pow(1 + $r_pre, $n) - 1) / $r_pre) * (1 + $r_pre);
        }

        $A = ($Corpus - $FV_current) / $factor;

        // --- Round for exact output ---
        $E_ret  = (int) round($E_ret);
        $Corpus = (int) round($Corpus);
        $A      = (int) round($A);

        return response()->json([
            'results' => [
                'inflation_adjusted_expense_at_retirement' => $E_ret,
                'corpus_required_at_retirement'            => $Corpus,
                'annual_investment_required_pre_retirement'=> $A
            ],
            'calculation_details' => [
                'years_until_retirement' => $n,
                'retirement_duration'    => $N,
                'inflation_rate_percent' => $inflation * 100,
                'inflation_rate_source'  => $inflationSource,
            ]
        ]);
    }
}
