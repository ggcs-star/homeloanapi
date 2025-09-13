<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Real_return_after_tax extends Controller
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

    protected function error(string $message, array $errorPayload = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'error'   => $errorPayload,
            'data'    => null,
        ], $code);
    }

    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'investment_amount' => 'required|numeric|min:0',
            'annual_return_rate' => 'required|numeric',
            'annual_inflation_rate' => 'required|numeric',
            'tax_rate_on_returns' => 'required|numeric|min:0|max:100',
            // Either duration_years + duration_months OR duration + duration_unit
            'duration_years' => 'nullable|numeric|min:0',
            'duration_months' => 'nullable|numeric|min:0|max:1200',
            'duration' => 'nullable|numeric|min:0',
            'duration_unit' => 'nullable|string|in:years,months',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // Inputs
        $P = (float) $request->input('investment_amount');
        $r_nominal_pct = (float) $request->input('annual_return_rate');
        $inflation_pct = (float) $request->input('annual_inflation_rate');
        $tax_pct = (float) $request->input('tax_rate_on_returns');

        $r_nominal = $r_nominal_pct / 100.0;
        $inflation = $inflation_pct / 100.0;
        $tax_rate = $tax_pct / 100.0;

        // ----------------------------
        // Duration handling
        // ----------------------------
        $fv_after_tax = 0.0;
        $real_fv = 0.0;
        $real_rate = 0.0;
        $r_after_tax = 0.0;

        if ($request->filled('duration_years') || $request->filled('duration_months')) {
            // Use years + months combo
            $years = (float) $request->input('duration_years', 0);
            $months = (float) $request->input('duration_months', 0);
            $n_years = $years + ($months / 12.0);

            $r_after_tax = $r_nominal * (1 - $tax_rate);
            $fv_after_tax = $P * pow(1 + $r_after_tax, $n_years);
            $real_fv = $fv_after_tax / pow(1 + $inflation, $n_years);
            $real_rate = (1 + $r_after_tax) / (1 + $inflation) - 1;
        } elseif ($request->filled('duration') && $request->filled('duration_unit')) {
            // Old logic fallback
            $duration = (float) $request->input('duration');
            $unit = $request->input('duration_unit');

            if ($unit === 'years') {
                $n_years = $duration;
                $r_after_tax = $r_nominal * (1 - $tax_rate);
                $fv_after_tax = $P * pow(1 + $r_after_tax, $n_years);
                $real_fv = $fv_after_tax / pow(1 + $inflation, $n_years);
                $real_rate = (1 + $r_after_tax) / (1 + $inflation) - 1;
            } else {
                $months = (int) round($duration);
                $monthly_nominal = $r_nominal / 12.0;
                $monthly_after_tax = $monthly_nominal * (1 - $tax_rate);
                $fv_after_tax = $P * pow(1 + $monthly_after_tax, $months);
                $monthly_inflation = $inflation / 12.0;
                $real_fv = $fv_after_tax / pow(1 + $monthly_inflation, $months);
                $real_rate = pow((1 + $monthly_after_tax) / (1 + $monthly_inflation), 12) - 1;
                $r_after_tax = $monthly_after_tax * 12; // approx annualized
            }
        }

        // ----------------------------
        // Round & return
        // ----------------------------
        $data = [
            'future_value_after_tax'       => round($fv_after_tax, 2),
            'real_future_value'            => round($real_fv, 2),
            'nominal_after_tax_percent'    => round($r_after_tax * 100, 2),
            'real_rate_of_return_percent'  => round($real_rate * 100, 2),
        ];

        return $this->success('Investment calculation completed successfully.', $data, 200);
    }
}
