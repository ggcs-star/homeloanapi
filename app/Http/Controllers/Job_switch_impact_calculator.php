<?php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;

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

    /**
     * Calculate stable-job vs job-switch total incomes (matching the screenshot logic).
     *
     * Expected JSON inputs:
     * - stable_current_annual_salary           (numeric)   // "Current Annual Salary (Stable Job) [₹]"
     * - stable_annual_increment_percent       (numeric)   // "Annual Increment (%)"
     * - stable_annual_bonus                   (numeric)   // "Annual Bonus/Benefits (₹)"
     *
     * - switch_starting_annual_salary         (numeric)   // "Starting Annual Salary (₹)"
     * - switch_salary_increase_percent        (numeric)   // "Salary Increase on Switch (%)"
     * - switch_number_of_switches             (integer)   // "Number of Job Switches"
     * - switch_avg_duration_per_job_years     (numeric)   // "Average Duration per Job (years)"
     * - switch_bonus_per_switch               (numeric)   // "Bonus per Switch (₹)"
     *
     * - analysis_period_years                 (integer)   // "Analysis Period (years)"
     * - expected_annual_investment_return_pct (numeric)   // "Expected Annual Investment Return (%)" (accepted but NOT used in the three output fields)
     *
     * Output (data):
     * - stable_total_income
     * - job_switch_total_income
     * - annual_difference   // (job_switch_total - stable_total) / analysis_period_years
     *
     * Notes:
     * - All monetary outputs are rounded to 2 decimals.
     * - Switch bonuses are applied when a switch happens (i.e., after each completed job block), if that switch occurs within the analysis window.
     */
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
            'expected_annual_investment_return_pct' => 'sometimes|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        try {
            // Stable inputs
            $stableSalary = (float)$request->input('stable_current_annual_salary');
            $stableIncPct = (float)$request->input('stable_annual_increment_percent') / 100.0;
            $stableBonus = (float)$request->input('stable_annual_bonus');

            // Switch inputs
            $switchStartSalary = (float)$request->input('switch_starting_annual_salary');
            $switchIncPct = (float)$request->input('switch_salary_increase_percent') / 100.0;
            $switchNum = (int)$request->input('switch_number_of_switches');
            $switchJobDuration = (float)$request->input('switch_avg_duration_per_job_years'); // years per job
            $switchBonus = (float)$request->input('switch_bonus_per_switch');

            // Analysis inputs
            $periodYears = (int)$request->input('analysis_period_years');
            // expected_annual_investment_return_pct accepted but not used for these fields
            $expectedReturnPct = $request->has('expected_annual_investment_return_pct')
                ? (float)$request->input('expected_annual_investment_return_pct') : null;

            // --------------------
            // Stable total calculation
            // --------------------
            // Year-by-year salary growth: S * (1+inc)^t for t=0..periodYears-1
            $stableTotal = 0.0;
            for ($y = 0; $y < $periodYears; $y++) {
                $yearSalary = $stableSalary * pow(1.0 + $stableIncPct, $y);
                $stableTotal += $yearSalary;
                // annual bonus each year (constant)
                $stableTotal += $stableBonus;
            }

            // --------------------
            // Job switch calculation (simulate year blocks)
            // --------------------
            $switchTotal = 0.0;
            $yearsRemaining = $periodYears;
            $currentSalary = $switchStartSalary;
            $switchesDone = 0;

            // We'll iterate job-block by job-block
            while ($yearsRemaining > 0) {
                // how many full years to take from this job block
                $blockYears = min($yearsRemaining, $switchJobDuration);

                // add salary for each year in this block
                // (salary remains constant across the block)
                $switchTotal += $currentSalary * $blockYears;

                // reduce remaining years
                $yearsRemaining -= $blockYears;

                // if we still have remaining years AND we are allowed to switch (switchesDone < switchNum)
                // then a switch occurs now (i.e., at the boundary), award bonus and increase salary
                if ($yearsRemaining > 0 && $switchesDone < $switchNum) {
                    // award bonus for the switch
                    $switchTotal += $switchBonus;

                    // increase salary for next block
                    $currentSalary = $currentSalary * (1.0 + $switchIncPct);

                    $switchesDone++;
                    // continue to next block
                } else {
                    // either no years left or no switches left: continue with same salary for remaining years (the while loop handles it)
                    // If no switches left but years remain, the loop will continue adding same salary until yearsRemaining becomes 0.
                    if ($yearsRemaining > 0 && $switchesDone >= $switchNum) {
                        // No switches left; add remaining years at currentSalary in next loop iteration
                        // (nothing to do here)
                    }
                }
            }

            // --------------------
            // Annual difference (per year)
            // --------------------
            $diffTotal = $switchTotal - $stableTotal;
            $annualDifference = $diffTotal / $periodYears;

            // Round to 2 decimals
            $round = fn($v) => round((float)$v, 2);

            $data = [
                'stable_total_income' => $round($stableTotal),
                'job_switch_total_income' => $round($switchTotal),
                'annual_difference' => $round($annualDifference),
            ];

            return $this->success('Job switch analysis completed.', $data, 200);
        } catch (\Throwable $ex) {
            Log::error('Exception in JobSwitchController::calculate', [
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
