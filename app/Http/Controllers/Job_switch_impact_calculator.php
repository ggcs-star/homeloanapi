<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Rate;

class Job_switch_impact_calculator extends Controller
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
            'stable_current_annual_salary' => 'required|numeric|min:0',
            'stable_annual_increment_percent' => 'required|numeric|min:0',
            'stable_annual_bonus' => 'required|numeric|min:0',

            'switch_starting_annual_salary' => 'required|numeric|min:0',
            'switch_salary_increase_percent' => 'required|numeric|min:0',
            'switch_number_of_switches' => 'required|integer|min:0',
            'switch_avg_duration_per_job_years' => 'required|numeric|min:0.1',
            'switch_bonus_per_switch' => 'required|numeric|min:0',

            'analysis_period_years' => 'required|integer|min:1',
            'expected_annual_investment_return_pct' => 'nullable|numeric|min:0', // ✅ optional now
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        try {
            // ✅ Fetch DB record
            $rateData = Rate::where('calculator', 'commision-calculator')->first();

            // --- Expected Return Rate ---
            if ($request->filled('expected_annual_investment_return_pct')) {
                $expectedReturnPct = (float) $request->input('expected_annual_investment_return_pct');
                $expectedReturnSource = 'user';
            } elseif ($rateData && isset($rateData->settings['loan_rate'])) {
                // Reusing loan_rate for expected return (adjust if you store differently)
                $expectedReturnPct = (float) $rateData->settings['loan_rate'];
                $expectedReturnSource = 'admin';
            } else {
                $expectedReturnPct = 8.0; // fallback
                $expectedReturnSource = 'system_default';
            }

            // Stable inputs
            $stableSalary = (float)$request->input('stable_current_annual_salary');
            $stableIncPct = (float)$request->input('stable_annual_increment_percent') / 100.0;
            $stableBonus = (float)$request->input('stable_annual_bonus');

            // Switch inputs
            $switchStartSalary = (float)$request->input('switch_starting_annual_salary');
            $switchIncPct = (float)$request->input('switch_salary_increase_percent') / 100.0;
            $switchNum = (int)$request->input('switch_number_of_switches');
            $switchJobDuration = (float)$request->input('switch_avg_duration_per_job_years');
            $switchBonus = (float)$request->input('switch_bonus_per_switch');

            // Analysis inputs
            $periodYears = (int)$request->input('analysis_period_years');

            // --------------------
            // Stable total calculation
            // --------------------
            $stableTotal = 0.0;
            for ($y = 0; $y < $periodYears; $y++) {
                $yearSalary = $stableSalary * pow(1.0 + $stableIncPct, $y);
                $stableTotal += $yearSalary + $stableBonus;
            }

            // --------------------
            // Job switch calculation
            // --------------------
            $switchTotal = 0.0;
            $yearsRemaining = $periodYears;
            $currentSalary = $switchStartSalary;
            $switchesDone = 0;

            while ($yearsRemaining > 0) {
                $blockYears = min($yearsRemaining, $switchJobDuration);
                $switchTotal += $currentSalary * $blockYears;
                $yearsRemaining -= $blockYears;

                if ($yearsRemaining > 0 && $switchesDone < $switchNum) {
                    $switchTotal += $switchBonus;
                    $currentSalary = $currentSalary * (1.0 + $switchIncPct);
                    $switchesDone++;
                }
            }

            // --------------------
            // Annual difference
            // --------------------
            $diffTotal = $switchTotal - $stableTotal;
            $annualDifference = $diffTotal / $periodYears;

            $round = fn($v) => round((float)$v, 2);

            $data = [
                'stable_total_income' => $round($stableTotal),
                'job_switch_total_income' => $round($switchTotal),
                'annual_difference' => $round($annualDifference),
                'rates_used' => [
                    'expected_annual_investment_return_pct' => $expectedReturnPct,
                    'expected_return_source' => $expectedReturnSource,
                ]
            ];

            return $this->success('Job switch analysis completed.', $data, 200);
        } catch (\Throwable $ex) {
            Log::error('Exception in Job_switch_impact_calculator::calculate', [
                'message' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error while computing job switch analysis. See logs.',
                'error' => ['exception' => $ex->getMessage()],
                'data' => null
            ], 500);
        }
    }
}
