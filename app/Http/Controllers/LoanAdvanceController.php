<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate; // ✅ DB model include

class LoanAdvanceController extends Controller
{
    public function calculate(Request $request)
    {
        try {
            $principal = (float) $request->input('principal'); // Loan amount
            $tenure = (float) $request->input('tenure');       // Term (years or months)
            $tenureType = strtolower($request->input('tenure_type', 'months')); // "years" or "months"
            $extraRepayment = (float) $request->input('extra_repayment', 0); // Extra monthly repayment

            // ✅ Interest Rate priority: User → DB → Fallback
            if ($request->filled('rate')) {
                $rate = (float) $request->input('rate');
                $rateSource = 'user';
            } else {
                $rateData = Rate::where('calculator', 'commision-calculator')->first();
                if ($rateData && isset($rateData->settings['loan_rate'])) {
                    $rate = (float) $rateData->settings['loan_rate'];
                    $rateSource = 'admin';
                } else {
                    $rate = 10.0; // fallback default
                    $rateSource = 'fallback';
                }
            }

            // ✅ Validate inputs
            if ($principal <= 0 || $rate <= 0 || $tenure <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input',
                    'error' => 'principal, rate, and tenure must be > 0',
                    'data' => null
                ], 422);
            }

            // ✅ Convert tenure into months & years
            if ($tenureType === 'years') {
                $tenureYears = $tenure;
                $tenureMonths = (int) ($tenure * 12);
            } else {
                $tenureMonths = (int) $tenure;
                $tenureYears = $tenureMonths / 12.0;
            }

            // ✅ EMI Formula
            $monthlyRate = $rate / (12 * 100);
            if ($monthlyRate == 0) {
                $emi = $principal / $tenureMonths;
            } else {
                $pow = pow(1 + $monthlyRate, $tenureMonths);
                $emi = $principal * $monthlyRate * $pow / ($pow - 1);
            }

            $balance = $principal;
            $totalInterest = 0;
            $month = 0;
            $schedule = [];

            // ✅ Amortization with extra repayment
            while ($balance > 0 && $month < $tenureMonths) {
                $month++;
                $interest = $balance * $monthlyRate;
                $principalComponent = $emi - $interest;

                // Add extra repayment
                $principalComponent += $extraRepayment;

                if ($principalComponent > $balance) {
                    $principalComponent = $balance; // last payment adjustment
                }

                $balance -= $principalComponent;
                $totalInterest += $interest;

                $schedule[] = [
                    'month' => $month,
                    'emi' => round($emi, 2),
                    'interest' => round($interest, 2),
                    'principal_paid' => round($principalComponent, 2),
                    'balance' => round(max($balance, 0), 2)
                ];

                if ($balance <= 0) break;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Loan amortization calculated successfully',
                'error' => null,
                'data' => [
                    'principal' => round($principal, 2),
                    'rate_percent' => $rate,
                    'rate_source' => $rateSource, // ✅ track source
                    'tenure_years' => round($tenureYears, 2),
                    'tenure_months' => $tenureMonths,
                    'tenure_type' => $tenureType,
                    'emi' => round($emi, 2),
                    'extra_repayment' => round($extraRepayment, 2),
                    'total_interest' => round($totalInterest, 2),
                    'total_payment' => round($principal + $totalInterest, 2),
                    'months_taken' => $month,
                    'schedule' => $schedule
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate loan amortization',
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
