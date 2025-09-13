<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InterestRate;

class LoanVsFdController extends Controller
{
    public function calculate(Request $request)
    {
        try {
            $principal = (float) $request->input('principal', 1000000);
            $inflation = (float) $request->input('inflation', 6);

            $term = (float) $request->input('term', 10);
            $termType = strtolower($request->input('term_type', 'years')); // "years" or "months"

            // ✅ Term calculation based on type
            if ($termType === 'months') {
                $termMonths = (int) $term;
                $termYears = $termMonths / 12.0;
            } else {
                $termYears = $term;
                $termMonths = (int) ($termYears * 12);
            }

            // ✅ Fetch rates from DB (fallback default if not found)
            $loanRate = InterestRate::where('type', 'loan')->value('rate') ?? 9;
            $fdRate   = InterestRate::where('type', 'fd')->value('rate') ?? 8;

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
                    ],
                    'rates_used' => [
                        'loan_rate_from_db' => $loanRate,
                        'fd_rate_from_db' => $fdRate
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
