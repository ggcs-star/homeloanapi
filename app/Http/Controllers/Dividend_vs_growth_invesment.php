<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate;

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

    public function compare(Request $request): JsonResponse
    {
        $rules = [
            'initial_investment' => 'required|numeric|min:0',
            'investment_horizon_years' => 'required|numeric|min:0',
            'annual_dividend_yield_percent' => 'nullable|numeric',
            'dividend_growth_rate_percent' => 'nullable|numeric',
            'dividend_tax_rate_percent' => 'nullable|numeric|min:0|max:100',
            'capital_growth_rate_percent' => 'nullable|numeric',
            'capital_gains_tax_rate_percent' => 'nullable|numeric|min:0|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // ✅ DB record
        $rateData = Rate::where('calculator', 'commision-calculator')->first();

        // Rates (user → admin → fallback)
        if ($request->filled('annual_dividend_yield_percent')) {
            $divYieldPct = (float) $request->input('annual_dividend_yield_percent');
            $divYieldSource = 'user';
        } else {
            $divYieldPct = $rateData->settings['loan_rate'] ?? 5; // fallback 5%
            $divYieldSource = 'admin';
        }

        if ($request->filled('dividend_growth_rate_percent')) {
            $divGrowPct = (float) $request->input('dividend_growth_rate_percent');
            $divGrowSource = 'user';
        } else {
            $divGrowPct = $rateData->settings['inflation_rate'] ?? 5; // fallback 5%
            $divGrowSource = 'admin';
        }

        if ($request->filled('dividend_tax_rate_percent')) {
            $divTaxPct = (float) $request->input('dividend_tax_rate_percent');
            $divTaxSource = 'user';
        } else {
            $divTaxPct = 10; // fallback 10%
            $divTaxSource = 'admin';
        }

        if ($request->filled('capital_growth_rate_percent')) {
            $capGrowPct = (float) $request->input('capital_growth_rate_percent');
            $capGrowSource = 'user';
        } else {
            $capGrowPct = $rateData->settings['loan_rate'] ?? 8; // fallback 8%
            $capGrowSource = 'admin';
        }

        if ($request->filled('capital_gains_tax_rate_percent')) {
            $cgTaxPct = (float) $request->input('capital_gains_tax_rate_percent');
            $cgTaxSource = 'user';
        } else {
            $cgTaxPct = 10; // fallback 10%
            $cgTaxSource = 'admin';
        }

        // Inputs
        $P = (float) $request->input('initial_investment');
        $years = (float) $request->input('investment_horizon_years');
        $n = (int) round($years);

        // convert percents to decimals
        $divYield = $divYieldPct / 100.0;
        $divGrow = $divGrowPct / 100.0;
        $divTax = $divTaxPct / 100.0;
        $capGrow = $capGrowPct / 100.0;
        $cgTax = $cgTaxPct / 100.0;

        // --- Dividend strategy
        $principal_end = $P * pow(1 + $capGrow, $n);

        if (abs($divGrow) < 1e-12) {
            $total_div_pre = $P * $divYield * $n;
        } else {
            $total_div_pre = $P * $divYield * (pow(1 + $divGrow, $n) - 1) / $divGrow;
        }

        $total_div_post = $total_div_pre * (1 - $divTax);
        $fv_dividend = $principal_end + $total_div_post;

        $effective_dividend_pct = ($n > 0 && $P > 0)
            ? round((pow($fv_dividend / $P, 1 / $n) - 1) * 100, 2)
            : null;

        // --- Growth strategy
        $fv_growth = $P * pow(1 + $capGrow, $n);
        $tax_on_gain = ($fv_growth - $P) * $cgTax;
        $fv_growth_after_tax = $fv_growth - $tax_on_gain;

        $effective_growth_pct = ($n > 0 && $P > 0)
            ? round((pow($fv_growth_after_tax / $P, 1 / $n) - 1) * 100, 2)
            : null;

        $data = [
            'dividend_based' => [
                'net_future_value' => round($fv_dividend, 2),
                'effective_annual_return_percent' => $effective_dividend_pct,
            ],
            'growth_based' => [
                'net_future_value' => round($fv_growth_after_tax, 2),
                'effective_annual_return_percent' => $effective_growth_pct,
            ],
            'rates_used' => [
                'annual_dividend_yield_percent' => $divYieldPct,
                'annual_dividend_yield_source' => $divYieldSource,
                'dividend_growth_rate_percent' => $divGrowPct,
                'dividend_growth_rate_source' => $divGrowSource,
                'dividend_tax_rate_percent' => $divTaxPct,
                'dividend_tax_rate_source' => $divTaxSource,
                'capital_growth_rate_percent' => $capGrowPct,
                'capital_growth_rate_source' => $capGrowSource,
                'capital_gains_tax_rate_percent' => $cgTaxPct,
                'capital_gains_tax_rate_source' => $cgTaxSource,
            ]
        ];

        return $this->success('Dividend vs Growth comparison completed.', $data, 200);
    }
}
