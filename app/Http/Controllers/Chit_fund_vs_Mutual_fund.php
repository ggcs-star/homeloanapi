<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Chit_fund_vs_Mutual_fund extends Controller
{
    

protected function success(string $message, array $data = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'error'   => null,
            'data'    => $data,
        ], $code);
    }

    protected function error(string $message, array $payload = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'error'   => $payload,
            'data'    => null,
        ], $code);
    }

    /**
     * POST /api/chit-vs-mutual/compare
     *
     * Matches screenshot derivation:
     * - Chit effective return = (net_gain / total_invested) * 100
     * - SIP FV uses monthlyRate = (effective_annual / 12) and annuity-due formula:
     *     FV = SIP * ((1+r)^n - 1)/r * (1 + r)
     * - SIP effective return = CAGR = (FV / invested)^(1/years) - 1
     */
    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'monthly_chit_amount' => 'required|numeric|min:0.01',
            'duration_months' => 'required|integer|min:1',
            'organizer_commission_percent' => 'required|numeric|min:0',
            'number_of_members' => 'required|integer|min:2',
            'expected_bid_discount_percent' => 'required|numeric|min:0',
            'expected_annual_return_percent' => 'required|numeric',
            'expense_ratio_percent' => 'required|numeric|min:0',
            'monthly_sip_amount' => 'nullable|numeric|min:0',
        ];

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return $this->error('Validation failed.', $v->errors()->toArray(), 422);
        }

        // Inputs
        $monthly = (float) $request->input('monthly_chit_amount');
        $nMonths = (int) $request->input('duration_months');
        $organizerPct = (float) $request->input('organizer_commission_percent') / 100.0;
        $members = (int) $request->input('number_of_members');
        $bidDiscPct = (float) $request->input('expected_bid_discount_percent') / 100.0;
        $annualReturnPct = (float) $request->input('expected_annual_return_percent');
        $expenseRatioPct = (float) $request->input('expense_ratio_percent');
        $sipMonthly = $request->filled('monthly_sip_amount') ? (float)$request->input('monthly_sip_amount') : $monthly;

        // --- CHIT FUND (screenshot-style) ---
        $grossPot = $monthly * $members;
        $commissionAmt = $grossPot * $organizerPct;
        $bidDiscountAmt = $grossPot * $bidDiscPct;

        // share distributed to you each month (winner excluded)
        $sharePerMonth = ($members - 1) > 0 ? ($bidDiscountAmt / ($members - 1)) : 0.0;
        $totalDividend = $sharePerMonth * $nMonths;
        $netGainChit = round($totalDividend - $commissionAmt, 2);
        $totalInvestedChit = $monthly * $nMonths;

        // effective return for chit = net_gain / total_invested * 100 (to match screenshot)
        $effectiveChitPct = $totalInvestedChit > 0
            ? round(($netGainChit / $totalInvestedChit) * 100, 2)
            : null;

        // --- MUTUAL FUND (SIP) (screenshot-style: annuity-due with monthlyRate = r_effective/12) ---
        // effective annual after expense (simple subtraction per screenshot)
        $r_effective = ($annualReturnPct - $expenseRatioPct) / 100.0;

        // monthly rate used in screenshot = r_effective / 12 (NOT pow conversion)
        $monthlyRate = $r_effective / 12.0;

        // SIP future value (annuity-due = contributions at beginning of period)
        if (abs($monthlyRate) < 1e-12) {
            // zero rate fallback
            $fvSip = $sipMonthly * $nMonths;
        } else {
            $fvOrdinary = $sipMonthly * (pow(1 + $monthlyRate, $nMonths) - 1) / $monthlyRate;
            // convert to annuity-due (multiply by (1+monthlyRate))
            $fvSip = $fvOrdinary * (1 + $monthlyRate);
        }

        $totalInvestedSip = $sipMonthly * $nMonths;
        $netGainSip = round($fvSip - $totalInvestedSip, 2);

        // effective SIP percent: compute CAGR over total years (1-year CAGR on amount you paid)
        $years = $nMonths / 12.0;
        if ($totalInvestedSip > 0 && $years > 0) {
            $effectiveSipPct = round((pow($fvSip / $totalInvestedSip, 1 / $years) - 1) * 100, 2);
        } else {
            $effectiveSipPct = null;
        }

        // Minimal response matching screenshot
        $data = [
            'chit' => [
                'net_gain' => $netGainChit,
                'effective_return_percent' => $effectiveChitPct,
            ],
            'mutual_fund_sip' => [
                'net_gain' => round($netGainSip, 2),
                'effective_return_percent' => $effectiveSipPct,
            ],
        ];

        return $this->success('Comparison updated to screenshot-style calculations.', $data, 200);
    }

    /**
     * (Optional) kept a simple xirr_by_periods if you ever want IRR in future.
     * Not used for screenshot-style numbers.
     */
    private function xirr_by_periods(array $cashflows, int $maxIter = 200, float $tol = 1e-9)
    {
        $npv = function ($r) use ($cashflows) {
            $sum = 0.0;
            foreach ($cashflows as $t => $cf) {
                if (1 + $r <= 0) {
                    return $r < -1 ? INF : -INF;
                }
                $sum += $cf / pow(1 + $r, $t);
            }
            return $sum;
        };

        $low = -0.999999;
        $high = 10.0;
        $npvLow = $npv($low);
        $npvHigh = $npv($high);

        $iter = 0;
        while ($npvLow * $npvHigh > 0 && $iter < 80) {
            $high *= 2;
            $npvHigh = $npv($high);
            $iter++;
        }

        if ($npvLow * $npvHigh > 0) {
            return null;
        }

        $iter = 0;
        while ($iter < $maxIter) {
            $mid = ($low + $high) / 2.0;
            $npvMid = $npv($mid);
            if (!is_finite($npvMid)) {
                return null;
            }
            if (abs($npvMid) < $tol) {
                return $mid;
            }
            if ($npvLow * $npvMid <= 0) {
                $high = $mid;
                $npvHigh = $npvMid;
            } else {
                $low = $mid;
                $npvLow = $npvMid;
            }
            $iter++;
        }

        return ($low + $high) / 2.0;
    }
}


