<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Dividend_vs_growth_invesment extends Controller
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
     * POST /api/dividend-vs-growth/compare
     *
     * Expected JSON body:
     * {
     *   "initial_investment": 10000,
     *   "investment_horizon_years": 8,
     *   "annual_dividend_yield_percent": 4,
     *   "dividend_growth_rate_percent": 5,
     *   "dividend_tax_rate_percent": 15,
     *   "capital_growth_rate_percent": 8,
     *   "capital_gains_tax_rate_percent": 10
     * }
     *
     * Returns minimal data: net_future_value and effective_annual_return_percent for both strategies.
     */
    public function compare(Request $request): JsonResponse
    {
        $rules = [
            'initial_investment' => 'required|numeric|min:0',
            'investment_horizon_years' => 'required|numeric|min:0',
            'annual_dividend_yield_percent' => 'required|numeric',
            'dividend_growth_rate_percent' => 'required|numeric',
            'dividend_tax_rate_percent' => 'required|numeric|min:0|max:100',
            'capital_growth_rate_percent' => 'required|numeric',
            'capital_gains_tax_rate_percent' => 'required|numeric|min:0|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // Inputs
        $P = (float) $request->input('initial_investment');
        $years = (float) $request->input('investment_horizon_years');
        $n = (int) round($years); // number of whole years for annual compounding
        $divYieldPct = (float) $request->input('annual_dividend_yield_percent');
        $divGrowPct = (float) $request->input('dividend_growth_rate_percent');
        $divTaxPct = (float) $request->input('dividend_tax_rate_percent');
        $capGrowPct = (float) $request->input('capital_growth_rate_percent');
        $cgTaxPct = (float) $request->input('capital_gains_tax_rate_percent');

        // convert percents to decimals
        $divYield = $divYieldPct / 100.0;
        $divGrow = $divGrowPct / 100.0;
        $divTax = $divTaxPct / 100.0;
        $capGrow = $capGrowPct / 100.0;
        $cgTax = $cgTaxPct / 100.0;

        // --- Dividend strategy (screenshot-style):
        // principal_end (no CGT applied in this model)
        $principal_end = $P * pow(1 + $capGrow, $n);

        // total dividends (pre-tax) = sum_{t=1..n} P * divYield * (1+divGrow)^(t-1)
        if (abs($divGrow) < 1e-12) {
            // if zero dividend-growth, it's simply P * divYield * n
            $total_div_pre = $P * $divYield * $n;
        } else {
            $total_div_pre = $P * $divYield * (pow(1 + $divGrow, $n) - 1) / $divGrow;
        }

        // after-tax dividends (received and NOT reinvested)
        $total_div_post = $total_div_pre * (1 - $divTax);

        // final total for dividend strategy
        $fv_dividend = $principal_end + $total_div_post;

        // effective annual return (CAGR) = (fv / P)^(1/n) - 1  (if n>0)
        $effective_dividend_pct = ($n > 0 && $P > 0)
            ? round((pow($fv_dividend / $P, 1 / $n) - 1) * 100, 2)
            : null;

        // --- Growth strategy:
        $fv_growth = $P * pow(1 + $capGrow, $n);
        // capital gains tax on gain only
        $tax_on_gain = ($fv_growth - $P) * $cgTax;
        $fv_growth_after_tax = $fv_growth - $tax_on_gain;

        $effective_growth_pct = ($n > 0 && $P > 0)
            ? round((pow($fv_growth_after_tax / $P, 1 / $n) - 1) * 100, 2)
            : null;

        // Prepare minimal response (fields user asked)
        $data = [
            'dividend_based' => [
                'net_future_value' => round($fv_dividend, 2),
                'effective_annual_return_percent' => $effective_dividend_pct,
            ],
            'growth_based' => [
                'net_future_value' => round($fv_growth_after_tax, 2),
                'effective_annual_return_percent' => $effective_growth_pct,
            ],
        ];

        return $this->success('Dividend vs Growth comparison completed.', $data, 200);
    }
}
