<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class Any_insurance_policy_evaluation extends Controller
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

    protected function error(string $message, array $errors = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'error'   => $errors,
            'data'    => null,
        ], $code);
    }

    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'premium_amount' => 'required|numeric|min:0.0001',
            'premium_frequency' => 'required|string|in:yearly,monthly,quarterly',
            'premium_paying_term_years' => 'required|numeric|min:1|max:100',
            'total_policy_term_years' => 'required|numeric|min:1|max:200',
            'sum_assured' => 'required|numeric|min:0',
            'expected_inflation_percent' => 'required|numeric|min:0|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // Inputs
        $premium = (float) $request->input('premium_amount');
        $frequency = strtolower($request->input('premium_frequency'));
        $payingYears = (int) $request->input('premium_paying_term_years');
        $totalYears = (int) $request->input('total_policy_term_years');
        $sumAssured = (float) $request->input('sum_assured');
        $inflationPct = (float) $request->input('expected_inflation_percent');

        // periods per year
        $periodsPerYear = match ($frequency) {
            'monthly' => 12,
            'quarterly' => 4,
            default => 1,
        };

        $totalPeriods = $totalYears * $periodsPerYear;
        $payingPeriods = $payingYears * $periodsPerYear;

        // Build cashflows (premiums outflow, maturity inflow)
        $cashflows = array_fill(0, $totalPeriods + 1, 0.0);
        for ($i = 0; $i < $payingPeriods; $i++) {
            $cashflows[$i] = -$premium;
        }
        $cashflows[$totalPeriods] = $sumAssured;

        // IRR Calculation
        $irr = $this->calculateIRR($cashflows);
        $annualRate = (pow(1 + $irr, $periodsPerYear) - 1) * 100;

        // PV of sum assured after inflation
        $pvSumAssured = $sumAssured / pow(1 + ($inflationPct / 100), $totalYears);

        $data = [
            'approx_annual_interest_rate_percent' => round($annualRate, 2),
            'present_value_of_sum_assured' => round($pvSumAssured, 2),
            'total_invested' => round($premium * $payingPeriods, 2),
            'sum_assured' => round($sumAssured, 2),
        ];

        return $this->success('Insurance Policy Evaluation calculated successfully.', $data);
    }

    private function calculateIRR(array $cashflows, float $guess = 0.1): float
    {
        $maxIterations = 1000;
        $tolerance = 1e-7;

        $rate = $guess;
        for ($i = 0; $i < $maxIterations; $i++) {
            $npv = 0.0;
            $derivative = 0.0;

            foreach ($cashflows as $t => $cf) {
                $npv += $cf / pow(1 + $rate, $t);
                if ($t > 0) {
                    $derivative += -$t * $cf / pow(1 + $rate, $t + 1);
                }
            }

            if (abs($npv) < $tolerance) {
                return $rate;
            }

            $newRate = $rate - $npv / $derivative;
            if (abs($newRate - $rate) < $tolerance) {
                return $newRate;
            }

            $rate = $newRate;
        }

        return $rate;
    }
}
