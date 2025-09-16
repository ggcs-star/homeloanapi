<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

class MarriageCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        // --- Inputs ---
        $present_cost = (float) $request->input('current_estimated_marriage_expense', 1000000);
        $years        = (int) $request->input('time_until_marriage', 5);
        $existing     = (float) $request->input('existing_savings', 200000);
        $frequency    = strtolower($request->input('savings_frequency', 'monthly'));

        // ✅ DB से fetch करो (commision-calculator)
        $rateData = Rate::where('calculator', 'commision-calculator')->first();
        $adminInflation = $rateData->settings['inflation_rate'] ?? null;
        $adminLoanRate  = $rateData->settings['loan_rate'] ?? null;

        // ✅ Inflation Rate (User → DB) (no fallback)
        if ($request->filled('expected_annual_inflation_rate')) {
            $inflation = (float) $request->input('expected_annual_inflation_rate') / 100;
            $inflationSource = 'user_input';
        } elseif ($adminInflation !== null) {
            $inflation = (float) $adminInflation / 100;
            $inflationSource = 'db_admin';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Inflation rate not provided by user or DB',
                'error' => 'missing_inflation_rate',
                'data' => null
            ], 422);
        }

        // ✅ Return Rate (User → DB) (no fallback)
        if ($request->filled('expected_annual_return_on_investment')) {
            $return = (float) $request->input('expected_annual_return_on_investment') / 100;
            $returnSource = 'user_input';
        } elseif ($adminLoanRate !== null) {
            $return = (float) $adminLoanRate / 100;
            $returnSource = 'db_admin';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Return rate not provided by user or DB',
                'error' => 'missing_return_rate',
                'data' => null
            ], 422);
        }

        // --- Step 1: Future Marriage Cost ---
        $future_cost = $present_cost * pow(1 + $inflation, $years);

        // --- Step 2: Future Value of Existing Savings ---
        $future_existing = $existing * pow(1 + $return, $years);

        // --- Step 3: Gap ---
        $gap = $future_cost - $future_existing;

        // --- Step 4: Required Savings ---
        if ($frequency === 'monthly') {
            $months = $years * 12;
            $r_monthly = pow(1 + $return, 1 / 12) - 1;
            $factor = (pow(1 + $r_monthly, $months) - 1) / $r_monthly;
            $pmt = $gap / $factor;
        } else { // annually
            $factor = (pow(1 + $return, $years) - 1) / $return;
            $pmt = $gap / $factor;
        }

        // --- Round values ---
        $future_cost     = round($future_cost);
        $future_existing = round($future_existing);
        $gap             = round($gap);
        $pmt             = round($pmt);

        // --- Response JSON ---
        return response()->json([
            'status' => 'success',
            'message' => 'Marriage calculation completed successfully',
            'error' => null,
            'data' => [
                'results' => [
                    'future_marriage_cost'          => $future_cost,
                    'future_value_existing_savings' => $future_existing,
                    'additional_amount_required'    => $gap,
                    'savings_required'              => $pmt,
                    'savings_frequency'             => ucfirst($frequency),
                ],
                'rates_used' => [
                    'inflation_rate_percent'  => $inflation * 100,
                    'inflation_rate_source'   => $inflationSource,
                    'return_rate_percent'     => $return * 100,
                    'return_rate_source'      => $returnSource,
                ],
                'explanation' => [
                    "The wedding cost of ₹" . number_format($present_cost) .
                    " is grown at " . ($inflation * 100) . "% p.a. for $years years, giving ₹" . number_format($future_cost) . ".",
                    "Your existing savings of ₹" . number_format($existing) .
                    " will grow at " . ($return * 100) . "% p.a. to ₹" . number_format($future_existing) . ".",
                    "That leaves a gap of ₹" . number_format($gap) . " to be filled by new investments.",
                    "To fill this gap, you need to save ₹" . number_format($pmt) . " per " . ucfirst($frequency) . "."
                ]
            ]
        ]);
    }
}
