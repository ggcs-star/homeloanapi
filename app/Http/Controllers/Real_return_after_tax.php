<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate;

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
            'tax_rate_on_returns' => 'required|numeric|min:0|max:100',

            // Duration fields
            'duration_years' => 'sometimes|numeric|min:0',
            'duration_months' => 'sometimes|numeric|min:0|max:1200',
            'duration' => 'sometimes|numeric|min:0',
            'duration_unit' => 'sometimes|string|in:years,months',

            // ✅ User input allowed
            'annual_return_rate' => 'sometimes|numeric|min:0',
            'annual_inflation_rate' => 'sometimes|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // ✅ DB से fetch (commission-calculator)
        $rateData = Rate::where('calculator', 'commision-calculator')->first();
        $adminReturn     = $rateData->settings['expected_return_rate'] ?? null;
        $adminLoanRate   = $rateData->settings['loan_rate'] ?? null;
        $adminInflation  = $rateData->settings['inflation_rate'] ?? null;

        // ✅ Return Rate (User → DB expected_return_rate → DB loan_rate → Error)
        if ($request->filled('annual_return_rate')) {
            $r_nominal_pct = (float) $request->input('annual_return_rate');
            $returnSource = 'user_input';
        } elseif ($adminReturn !== null) {
            $r_nominal_pct = (float) $adminReturn;
            $returnSource = 'db_expected_return_rate';
        } elseif ($adminLoanRate !== null) {
            $r_nominal_pct = (float) $adminLoanRate;
            $returnSource = 'db_loan_rate';
        } else {
            return $this->error('Annual return rate not provided in request or DB.', [], 422);
        }

        // ✅ Inflation Rate (User → DB → Error)
        if ($request->filled('annual_inflation_rate')) {
            $inflation_pct = (float) $request->input('annual_inflation_rate');
            $inflationSource = 'user_input';
        } elseif ($adminInflation !== null) {
            $inflation_pct = (float) $adminInflation;
            $inflationSource = 'db_admin';
        } else {
            return $this->error('Annual inflation rate not provided in request or DB.', [], 422);
        }

        // ✅ Tax rate
        $tax_pct = (float) $request->input('tax_rate_on_returns');

        // ✅ Conversions
        $P = (float) $request->input('investment_amount');
        $r_nominal = $r_nominal_pct / 100.0;
        $inflation = $inflation_pct / 100.0;
        $tax_rate = $tax_pct / 100.0;

        $fv_after_tax = 0.0;
        $real_fv = 0.0;
        $real_rate = 0.0;
        $r_after_tax = 0.0;

        // ✅ Duration handling
        if ($request->filled('duration_years') || $request->filled('duration_months')) {
            $years = (float) $request->input('duration_years', 0);
            $months = (float) $request->input('duration_months', 0);
            $n_years = $years + ($months / 12.0);

            $r_after_tax = $r_nominal * (1 - $tax_rate);
            $fv_after_tax = $P * pow(1 + $r_after_tax, $n_years);
            $real_fv = $fv_after_tax / pow(1 + $inflation, $n_years);
            $real_rate = (1 + $r_after_tax) / (1 + $inflation) - 1;
        } elseif ($request->filled('duration') && $request->filled('duration_unit')) {
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

        $data = [
            'future_value_after_tax'       => round($fv_after_tax, 2),
            'real_future_value'            => round($real_fv, 2),
            'nominal_after_tax_percent'    => round($r_after_tax * 100, 2),
            'real_rate_of_return_percent'  => round($real_rate * 100, 2),
            'rates_used' => [
                'return_rate_percent'   => $r_nominal_pct,
                'return_rate_source'    => $returnSource,
                'inflation_rate_percent'=> $inflation_pct,
                'inflation_rate_source' => $inflationSource,
            ]
        ];

        return $this->success('Investment calculation completed successfully.', $data, 200);
    }
}
