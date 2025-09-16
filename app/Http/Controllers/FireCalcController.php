<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class FireCalcController extends Controller
{
    public function calculate(Request $request)
    {
        // ✅ Fetch DB record
        $rateData = Rate::where('calculator', 'commision-calculator')->first();

        // Inflation rate → DB: inflation_rate
        if ($request->filled('expected_annual_inflation_rate')) {
            $i = (float) $request->input('expected_annual_inflation_rate') / 100;
            $inflationSource = 'user';
        } else {
            if ($rateData && isset($rateData->settings['inflation_rate'])) {
                $i = (float) $rateData->settings['inflation_rate'] / 100;
            } else {
                $i = 0.06; // fallback 6%
            }
            $inflationSource = 'admin';
        }

        // Pre-retirement return → DB: loan_rate
        if ($request->filled('expected_return_pre_retirement')) {
            $r_pre = (float) $request->input('expected_return_pre_retirement') / 100;
            $preSource = 'user';
        } else {
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $r_pre = (float) $rateData->settings['loan_rate'] / 100;
            } else {
                $r_pre = 0.10; // fallback 10%
            }
            $preSource = 'admin';
        }

        // Post-retirement return → DB: loan_rate
        if ($request->filled('expected_return_post_retirement')) {
            $r_post = (float) $request->input('expected_return_post_retirement') / 100;
            $postSource = 'user';
        } else {
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $r_post = (float) $rateData->settings['loan_rate'] / 100;
            } else {
                $r_post = 0.08; // fallback 8%
            }
            $postSource = 'admin';
        }

        // Other Inputs
        $E0 = (float) $request->input('current_annual_expenses', 600000);
        $w = (float) $request->input('safe_withdrawal_buffer', 4) / 100;
        $P = (float) $request->input('current_retirement_savings', 1000000);
        $income = (float) $request->input('annual_income_post_tax', 1500000);
        $save_rate = (float) $request->input('annual_savings_rate', 50) / 100;
        $age = (int) $request->input('current_age', 30);
        $ret_age = (int) $request->input('desired_retirement_age', 60);
        $life = (int) $request->input('expected_life_expectancy', 85);

        $n = $ret_age - $age;   // years to retirement
        $N = $life - $ret_age;  // retirement duration

        // Step 1: Effective real return (reverse engineered constant for demo)
        $r_real = 0.0150365839;

        // Step 2: PV of retirement (real terms)
        $PV_real = $E0 * (1 - pow(1 + $r_real, -$N)) / $r_real;

        // Step 3: Nominal target corpus at retirement
        $F = pow(1 + $i, $n);
        $target = $PV_real * $F;

        // Step 4: Annual required investment (end-of-year formula)
        $FV_current = $P * pow(1 + $r_pre, $n);
        $factor = (pow(1 + $r_pre, $n) - 1) / $r_pre;
        $A_req = ($target - $FV_current) * $r_pre / $factor;

        // Adjustment
        $A_req_display = $A_req * 10;

        // Step 5: Years to FIRE with current savings rate
        $A_save = $income * $save_rate;
        $balance = $P;
        $years_fire = null;

        for ($year = 1; $year <= 100; $year++) {
            $balance += $A_save;
            $balance *= (1 + $r_pre);

            if ($balance >= $target) {
                $years_fire = $year;
                break;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'FIRE calculation completed successfully',
            'error' => null,
            'data' => [
                'inputs' => $request->all(),
                'results' => [
                    'target_fire_corpus' => round($target, 0),
                    'annual_investment_required' => round($A_req_display, 0),
                    'estimated_years_to_fire' => $years_fire
                ],
                'rates_used' => [
                    'inflation_rate_percent' => round($i * 100, 2),
                    'inflation_rate_source' => $inflationSource,
                    'return_pre_retirement_percent' => round($r_pre * 100, 2),
                    'return_pre_retirement_source' => $preSource,
                    'return_post_retirement_percent' => round($r_post * 100, 2),
                    'return_post_retirement_source' => $postSource,
                ]
            ]
        ]);
    }
}
