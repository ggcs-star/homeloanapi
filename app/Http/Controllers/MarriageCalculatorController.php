<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MarriageCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        // --- Inputs ---
        $present_cost = (float) $request->input('current_estimated_marriage_expense', 1000000);
        $years = (int) $request->input('time_until_marriage', 5);
        $inflation = (float) $request->input('expected_annual_inflation_rate', 8) / 100;
        $return = (float) $request->input('expected_annual_return_on_investment', 10) / 100;
        $existing = (float) $request->input('existing_savings', 200000);
        $frequency = strtolower($request->input('savings_frequency', 'monthly'));

        // --- Step 1: Future Marriage Cost ---
        $future_cost = $present_cost * pow(1 + $inflation, $years);

        // --- Step 2: Future Value of Existing Savings ---
        $future_existing = $existing * pow(1 + $return, $years);

        // --- Step 3: Gap ---
        $gap = $future_cost - $future_existing;

        // --- Step 4: Required Savings ---
        if ($frequency === 'monthly') {
            $months = $years * 12;
            // precise monthly compounding rate
            $r_monthly = pow(1 + $return, 1 / 12) - 1;
            $factor = (pow(1 + $r_monthly, $months) - 1) / $r_monthly;
            $pmt = $gap / $factor;
        } else { // annually
            $factor = (pow(1 + $return, $years) - 1) / $return;
            $pmt = $gap / $factor;
        }

        // --- Round values ---
        $future_cost = round($future_cost);
        $future_existing = round($future_existing);
        $gap = round($gap);
        $pmt = round($pmt);

        // --- Response JSON ---
        return response()->json([
            'results' => [
                'future_marriage_cost' => $future_cost,          // 1,469,328
                'future_value_existing_savings' => $future_existing, // 322,102
                'additional_amount_required' => $gap,           // 1,147,226
                'savings_required' => $pmt,
                'savings_frequency' => ucfirst($frequency)
            ],
            'explanation' => [
                "The wedding cost of ₹" . number_format($present_cost) . 
                " is grown at " . ($inflation*100) . "% p.a. for $years years, giving ₹" . number_format($future_cost) . ".",
                "Your existing savings of ₹" . number_format($existing) . 
                " will grow at " . ($return*100) . "% p.a. to ₹" . number_format($future_existing) . ".",
                "That leaves a gap of ₹" . number_format($gap) . " to be filled by new investments.",
                "To fill this gap, you need to save ₹" . number_format($pmt) . " per " . ucfirst($frequency) . "."
            ]
        ]);
    }
}
