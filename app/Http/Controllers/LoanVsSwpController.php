<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

class LoanVsSwpController extends Controller
{
    public function calculate(Request $request)
    {
        try {
            $P = (float) $request->input('principal', 0);
            $years = (int) $request->input('tenure_years', 0);

            // ✅ DB से admin defaults लाओ
            $rateData = Rate::where('calculator', 'commision-calculator')->first();
            $adminLoanRate = $rateData->settings['loan_rate'] ?? null;

            // ✅ User input > Admin default > Fallback
            if ($request->filled('loan_rate_percent')) {
                $loanRate = (float) $request->input('loan_rate_percent');
                $loanRateSource = 'user_input';
            } elseif ($adminLoanRate !== null) {
                $loanRate = (float) $adminLoanRate;
                $loanRateSource = 'admin_default';
            } else {
                $loanRate = 10.0; // fallback
                $loanRateSource = 'system_default';
            }

            if ($P <= 0 || $loanRate <= 0 || $years <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'principal, loan_rate_percent and tenure_years must be > 0',
                    'error' => 'principal, loan_rate_percent and tenure_years must be > 0',
                    'data' => null
                ], 422);
            }

            $n = $years * 12;
            $rLoanMonthly = $loanRate / 12.0 / 100.0;

            // exact EMI (full precision)
            $pow = pow(1 + $rLoanMonthly, $n);
            $emi_raw = $P * $rLoanMonthly * $pow / ($pow - 1);
            $emi_display = round($emi_raw, 2);

            $finalBalance = function ($Rannual) use ($P, $emi_raw, $years) {
                $bal = $P;
                for ($y = 0; $y < $years; $y++) {
                    $interestSum = 0.0;
                    for ($m = 0; $m < 12; $m++) {
                        $bal -= $emi_raw;
                        $interestSum += $bal * ($Rannual / 12.0);
                    }
                    $bal += $interestSum;
                }
                return $bal;
            };

            // Bisection bracket
            $low = 0.0;
            $high = 1.0;
            $fLow = $finalBalance($low);
            $fHigh = $finalBalance($high);

            $iterExpand = 0;
            while ($fLow * $fHigh > 0 && $iterExpand < 60) {
                $high *= 2.0;
                $fHigh = $finalBalance($high);
                $iterExpand++;
            }

            if ($fLow * $fHigh > 0) {
                $requiredAnnualPercent = round($rLoanMonthly * 12.0 * 100.0, 4);
                return response()->json([
                    'status' => 'success',
                    'message' => 'Could not bracket root; returned loan annual rate as fallback',
                    'error' => null,
                    'data' => [
                        'principal' => $P,
                        'loan_rate_percent' => $loanRate,
                        'loan_rate_source' => $loanRateSource,
                        'tenure_years' => $years,
                        'emi' => $emi_display,
                        'required_rate_percent_per_year' => $requiredAnnualPercent
                    ]
                ]);
            }

            // Bisection method
            $tol = 1e-12;
            $rMid = 0.0;
            for ($i = 0; $i < 200; $i++) {
                $rMid = ($low + $high) / 2.0;
                $fMid = $finalBalance($rMid);
                if (abs($fMid) < 1e-9) break;
                if ($fLow * $fMid <= 0) {
                    $high = $rMid;
                    $fHigh = $fMid;
                } else {
                    $low = $rMid;
                    $fLow = $fMid;
                }
                if (($high - $low) < $tol) break;
            }

            $requiredAnnualPercent = round($rMid * 100.0, 4);

            return response()->json([
                'status' => 'success',
                'message' => 'Loan vs SWP calculation completed successfully',
                'error' => null,
                'data' => [
                    'principal' => $P,
                    'loan_rate_percent' => $loanRate,
                    'loan_rate_source' => $loanRateSource,
                    'tenure_years' => $years,
                    'emi' => $emi_display,
                    'required_rate_percent_per_year' => $requiredAnnualPercent
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error in LoanVsSwpController',
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
