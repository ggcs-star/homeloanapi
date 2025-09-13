<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EmiPrepayController extends Controller
{
    public function calculate(Request $request)
    {
        try {
            // Inputs
            $loan_amount   = (float) $request->input('loan_amount', 1000000);
            $loan_rate     = (float) $request->input('loan_rate', 8.5);
            $tenure        = (float) $request->input('tenure', 240); // default 240 months = 20 years
            $tenure_type   = strtolower($request->input('tenure_type', 'months')); // "years" or "months"
            $prepayment    = (float) $request->input('prepayment', 200000);
            $invest_rate   = (float) $request->input('invest_rate', 12);

            // ✅ Term calculation based on type
            if ($tenure_type === 'years') {
                $tenure_years  = $tenure;
                $tenure_months = (int) ($tenure * 12);
            } else {
                $tenure_months = (int) $tenure;
                $tenure_years  = $tenure_months / 12.0;
            }

            $monthly_rate = $loan_rate / 12 / 100;

            // EMI (original)
            $emi = ($loan_amount * $monthly_rate * pow(1 + $monthly_rate, $tenure_months)) /
                   (pow(1 + $monthly_rate, $tenure_months) - 1);

            $total_payment_original = $emi * $tenure_months;
            $total_interest_original = $total_payment_original - $loan_amount;

            // After prepayment (reduce tenure, keep EMI same)
            $remaining_loan = $loan_amount - $prepayment;
            $months_after_prepay = log($emi / ($emi - $remaining_loan * $monthly_rate)) / log(1 + $monthly_rate);

            $total_payment_prepay = $emi * $months_after_prepay + $prepayment;
            $total_interest_prepay = $total_payment_prepay - $loan_amount;
            $interest_saved = $total_interest_original - $total_interest_prepay;

            // Investment case
            $future_value_invest = $prepayment * pow(1 + ($invest_rate / 100), $tenure_years);
            $investment_interest = $future_value_invest - $prepayment;

            // Decision
            $decision = $investment_interest > $interest_saved
                ? "Investing the lumpsum yields more (Rs. " . number_format($investment_interest, 2) . ") than the interest saved from prepayment (Rs. " . number_format($interest_saved, 2) . ")."
                : "Prepayment saves more interest (Rs. " . number_format($interest_saved, 2) . ") than investing (Rs. " . number_format($investment_interest, 2) . ").";

            return response()->json([
                'status' => 'success',
                'message' => 'EMI prepayment calculation completed successfully',
                'error' => null,
                'data' => [
                    "input" => [
                        "loan_amount" => $loan_amount,
                        "loan_rate_percent" => $loan_rate,
                        "tenure_years" => round($tenure_years, 2),
                        "tenure_months" => $tenure_months,
                        "tenure_type" => $tenure_type,
                        "prepayment" => $prepayment,
                        "invest_rate_percent" => $invest_rate,
                    ],
                    "scenario" => [
                        "prepayment_savings" => round($interest_saved, 2),
                        "investment_future_value" => round($future_value_invest, 2),
                        "investment_returns" => round($investment_interest, 2)
                    ],
                    "decision" => $decision,
                    "calculation_details" => [
                        "original_emi" => round($emi, 2),
                        "new_emi_after_prepayment" => "Same EMI, finishing in " . round($months_after_prepay, 1) . " months",
                        "total_interest_original" => round($total_interest_original, 2),
                        "total_interest_after_prepayment" => round($total_interest_prepay, 2),
                        "interest_saved" => round($interest_saved, 2),
                        "future_value_investment" => round($future_value_invest, 2),
                        "interest_earned_on_investment" => round($investment_interest, 2),
                    ],
                    "scenario_1_no_prepayment" => [
                        "outstanding_loan" => $loan_amount,
                        "monthly_interest_rate" => round($monthly_rate * 100, 3),
                        "remaining_tenure_months" => $tenure_months,
                        "emi_original" => round($emi, 2),
                        "total_interest_original" => round($total_interest_original, 2),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate EMI prepayment',
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
