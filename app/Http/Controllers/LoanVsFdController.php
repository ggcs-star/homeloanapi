<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate; // ✅ DB से rates लाने के लिए

class LoanVsFdController extends Controller
{
    public function calculate(Request $request)
    {
        try {
            // ✅ Principal user input (default: 10,00,000)
            $principal = (float) $request->input('principal', 1000000);

            $term = (float) $request->input('term', 10);
            $termType = strtolower($request->input('term_type', 'years'));

            // ✅ Term conversion
            if ($termType === 'months') {
                $termMonths = (int) $term;
                $termYears = $termMonths / 12.0;
            } else {
                $termYears = $term;
                $termMonths = (int) ($termYears * 12);
            }

            // ✅ Current controller name as calculator key
            $calculatorName = class_basename(__CLASS__);

            // ✅ DB से admin defaults लाओ
            $rateData = Rate::where('calculator', 'commision-calculator')->first();

            $adminLoanRate      = $rateData->settings['loan_rate']      ?? null;
            $adminFdRate        = $rateData->settings['fd_rate']        ?? null;
            $adminInflationRate = $rateData->settings['inflation_rate'] ?? null;

            // ✅ User input > Admin default > System fallback
            $loanRate = $request->filled('loan_rate')
                ? (float) $request->input('loan_rate')
                : ($adminLoanRate ?? 10.0);

            $fdRate = $request->filled('fd_rate')
                ? (float) $request->input('fd_rate')
                : ($adminFdRate ?? 8.0);

            $inflationRate = $request->filled('inflation_rate')
                ? (float) $request->input('inflation_rate')
                : ($adminInflationRate ?? 5.0);

            // ---------- Loan calculation ----------
            $monthlyRate = $loanRate / 12 / 100;
            if ($monthlyRate == 0) {
                $emi = $principal / $termMonths;
            } else {
                $pow = pow(1 + $monthlyRate, $termMonths);
                $emi = $principal * $monthlyRate * $pow / ($pow - 1);
            }
            $totalPaid = $emi * $termMonths;
            $totalInterest = $totalPaid - $principal;

            // ---------- FD calculation ----------
            $fdMaturity = $principal * pow(1 + $fdRate / 100, $termYears);
            $fdInterest = $fdMaturity - $principal;

            // ---------- Inflation adjustment ----------
            $inflationFactor = pow(1 + $inflationRate / 100, $termYears);
            $fdRealValue = $fdMaturity / $inflationFactor;

            // ✅ Source check
            $loanRateSource = $request->filled('loan_rate')
                ? 'user_input'
                : ($adminLoanRate !== null ? 'admin_default' : 'system_default');

            $fdRateSource = $request->filled('fd_rate')
                ? 'user_input'
                : ($adminFdRate !== null ? 'admin_default' : 'system_default');

            $inflationRateSource = $request->filled('inflation_rate')
                ? 'user_input'
                : ($adminInflationRate !== null ? 'admin_default' : 'system_default');

            return response()->json([
                'status' => 'success',
                'message' => 'Loan vs FD calculation completed successfully',
                'error' => null,
                'data' => [
                    'term' => [
                        'years' => round($termYears, 2),
                        'months' => $termMonths,
                        'type' => $termType
                    ],
                    'loan_details' => [
                        'monthly_emi' => round($emi, 2),
                        'total_interest_paid' => round($totalInterest, 2),
                        'total_amount_paid' => round($totalPaid, 2),
                    ],
                    'fd_details' => [
                        'total_interest_earned' => round($fdInterest, 2),
                        'fd_maturity' => round($fdMaturity, 2),
                        'real_value_after_inflation' => round($fdRealValue, 2),
                    ],
                    'rates_used' => [
                        'loan_rate' => $loanRate,
                        'loan_rate_source' => $loanRateSource,
                        'fd_rate' => $fdRate,
                        'fd_rate_source' => $fdRateSource,
                        'inflation_rate' => $inflationRate,
                        'inflation_rate_source' => $inflationRateSource,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate Loan vs FD',
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
