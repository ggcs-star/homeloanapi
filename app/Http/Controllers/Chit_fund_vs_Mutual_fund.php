<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate;

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

    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'monthly_chit_amount' => 'required|numeric|min:0.01',
            'duration_months' => 'required|integer|min:1',
            'organizer_commission_percent' => 'required|numeric|min:0',
            'number_of_members' => 'required|integer|min:2',
            'expected_bid_discount_percent' => 'required|numeric|min:0',
            'expected_annual_return_percent' => 'nullable|numeric', // optional now
            'expense_ratio_percent' => 'nullable|numeric|min:0',    // optional now
            'monthly_sip_amount' => 'nullable|numeric|min:0',
        ];

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return $this->error('Validation failed.', $v->errors()->toArray(), 422);
        }

        // ✅ Fetch from DB if not provided by user
        $rateData = Rate::where('calculator', 'commision-calculator')->first();

        // Annual Return
        if ($request->filled('expected_annual_return_percent')) {
            $annualReturnPct = (float) $request->input('expected_annual_return_percent');
            $annualReturnSource = 'user';
        } elseif ($rateData && isset($rateData->settings['loan_rate'])) {
            $annualReturnPct = (float) $rateData->settings['loan_rate'];
            $annualReturnSource = 'admin';
        } else {
            $annualReturnPct = 10.0; // fallback
            $annualReturnSource = 'admin';
        }

        // Expense Ratio
        if ($request->filled('expense_ratio_percent')) {
            $expenseRatioPct = (float) $request->input('expense_ratio_percent');
            $expenseSource = 'user';
        } elseif ($rateData && isset($rateData->settings['inflation_rate'])) {
            // reuse inflation_rate as proxy for expense ratio if present
            $expenseRatioPct = (float) $rateData->settings['inflation_rate'];
            $expenseSource = 'admin';
        } else {
            $expenseRatioPct = 0.0; // fallback
            $expenseSource = 'admin';
        }

        // Inputs
        $monthly = (float) $request->input('monthly_chit_amount');
        $nMonths = (int) $request->input('duration_months');
        $organizerPct = (float) $request->input('organizer_commission_percent') / 100.0;
        $members = (int) $request->input('number_of_members');
        $bidDiscPct = (float) $request->input('expected_bid_discount_percent') / 100.0;
        $sipMonthly = $request->filled('monthly_sip_amount') ? (float)$request->input('monthly_sip_amount') : $monthly;

        // --- CHIT FUND ---
        $grossPot = $monthly * $members;
        $commissionAmt = $grossPot * $organizerPct;
        $bidDiscountAmt = $grossPot * $bidDiscPct;

        $sharePerMonth = ($members - 1) > 0 ? ($bidDiscountAmt / ($members - 1)) : 0.0;
        $totalDividend = $sharePerMonth * $nMonths;
        $netGainChit = round($totalDividend - $commissionAmt, 2);
        $totalInvestedChit = $monthly * $nMonths;

        $effectiveChitPct = $totalInvestedChit > 0
            ? round(($netGainChit / $totalInvestedChit) * 100, 2)
            : null;

        // --- MUTUAL FUND (SIP) ---
        $r_effective = ($annualReturnPct - $expenseRatioPct) / 100.0;
        $monthlyRate = $r_effective / 12.0;

        if (abs($monthlyRate) < 1e-12) {
            $fvSip = $sipMonthly * $nMonths;
        } else {
            $fvOrdinary = $sipMonthly * (pow(1 + $monthlyRate, $nMonths) - 1) / $monthlyRate;
            $fvSip = $fvOrdinary * (1 + $monthlyRate);
        }

        $totalInvestedSip = $sipMonthly * $nMonths;
        $netGainSip = round($fvSip - $totalInvestedSip, 2);

        $years = $nMonths / 12.0;
        if ($totalInvestedSip > 0 && $years > 0) {
            $effectiveSipPct = round((pow($fvSip / $totalInvestedSip, 1 / $years) - 1) * 100, 2);
        } else {
            $effectiveSipPct = null;
        }

        $data = [
            'chit' => [
                'net_gain' => $netGainChit,
                'effective_return_percent' => $effectiveChitPct,
            ],
            'mutual_fund_sip' => [
                'net_gain' => round($netGainSip, 2),
                'effective_return_percent' => $effectiveSipPct,
            ],
            'rates_used' => [
                'annual_return_percent' => $annualReturnPct,
                'annual_return_source'  => $annualReturnSource,
                'expense_ratio_percent' => $expenseRatioPct,
                'expense_ratio_source'  => $expenseSource,
            ]
        ];

        return $this->success('Comparison updated with DB/user fallback.', $data, 200);
    }
}
