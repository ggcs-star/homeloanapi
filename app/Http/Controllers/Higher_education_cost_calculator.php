<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class Higher_education_cost_calculator extends Controller
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
     * Degree ROI calculation - returns exactly the fields shown in the screenshot:
     * - total_direct_cost
     * - loan_interest_paid
     * - opportunity_cost
     * - total_investment
     * - pv_additional_earnings
     * - roi_percent
     *
     * Expected JSON inputs:
     * - tuition_fees
     * - additional_costs
     * - loan_amount
     * - loan_interest_rate_pct    (annual %)
     * - loan_repayment_period_years
     * - program_duration_years
     * - opportunity_cost_per_year
     * - starting_salary_after_degree
     * - salary_growth_rate_pct
     * - baseline_salary_without_degree
     * - baseline_salary_growth_rate_pct
     * - career_duration_years
     * - discount_inflation_rate_pct
     */
   public function calculate(Request $request): JsonResponse
{
    $rules = [
        'tuition_fees' => 'required|numeric|min:0',
        'additional_costs' => 'required|numeric|min:0',
        'loan_amount' => 'required|numeric|min:0',
        'loan_interest_rate_pct' => 'required|numeric|min:0',
        'loan_repayment_period_years' => 'required|numeric|min:0.5',

        'program_duration_years' => 'required|numeric|min:0',
        'opportunity_cost_per_year' => 'required|numeric|min:0',

        'starting_salary_after_degree' => 'required|numeric|min:0',
        'salary_growth_rate_pct' => 'required|numeric|min:0',

        'baseline_salary_without_degree' => 'required|numeric|min:0',
        'baseline_salary_growth_rate_pct' => 'required|numeric|min:0',

        'career_duration_years' => 'required|numeric|min:1',
        'discount_inflation_rate_pct' => 'required|numeric|min:0',
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
        return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
    }

    try {
        // Inputs
        $tuition = (float)$request->input('tuition_fees');
        $additional = (float)$request->input('additional_costs');

        $loanAmount = (float)$request->input('loan_amount');
        $loanRatePct = (float)$request->input('loan_interest_rate_pct');
        $loanYears = (float)$request->input('loan_repayment_period_years');

        $programYears = (float)$request->input('program_duration_years');
        $oppCostPerYear = (float)$request->input('opportunity_cost_per_year');

        $startSalary = (float)$request->input('starting_salary_after_degree');
        $salaryGrowthPct = (float)$request->input('salary_growth_rate_pct');

        $baselineSalary = (float)$request->input('baseline_salary_without_degree');
        $baselineGrowthPct = (float)$request->input('baseline_salary_growth_rate_pct');

        $careerYears = (int)$request->input('career_duration_years');
        $discountPct = (float)$request->input('discount_inflation_rate_pct');

        // 1) Total Direct Cost
        $totalDirectCost = $tuition + $additional;

        // 2) Loan interest paid (amortization monthly)
        $loanInterestPaid = 0.0;
        if ($loanAmount > 0 && $loanYears > 0) {
            $monthlyRate = ($loanRatePct / 100.0) / 12.0;
            $n = (int)round($loanYears * 12);
            if ($monthlyRate == 0.0) {
                $monthlyPayment = $loanAmount / max(1, $n);
            } else {
                $monthlyPayment = $loanAmount * $monthlyRate / (1 - pow(1 + $monthlyRate, -$n));
            }
            $totalPaid = $monthlyPayment * $n;
            $loanInterestPaid = $totalPaid - $loanAmount;
        }

        // 3) Opportunity Cost
        $opportunityCost = $oppCostPerYear * $programYears;

        // 4) Total Investment
        $totalInvestment = $totalDirectCost + $loanInterestPaid + $opportunityCost;

        // 5) PV of Additional Earnings
        $pvAdditional = 0.0;
        $salaryGrowthR = $salaryGrowthPct / 100.0;
        $baselineGrowthR = $baselineGrowthPct / 100.0;
        $discountR = $discountPct / 100.0;

        for ($y = 1; $y <= $careerYears; $y++) {
            $salaryYear = $startSalary * pow(1 + $salaryGrowthR, $y - 1);
            $baselineYear = $baselineSalary * pow(1 + $baselineGrowthR, $y - 1);
            $additionalEarning = $salaryYear - $baselineYear;
            $pvAdditional += $additionalEarning / pow(1 + $discountR, $y);
        }

        // 6) ROI %
        $roiPercent = 0.0;
        if ($totalInvestment > 0) {
            $roiPercent = (($pvAdditional - $totalInvestment) / $totalInvestment) * 100.0;
        }

        // ---- FIXED ROUND FUNCTION ----
       $round = fn($v, $precision = 0) => round((float)$v, $precision);

$data = [
    'total_direct_cost'      => $round($totalDirectCost, 0),
    'loan_interest_paid'     => $round($loanInterestPaid, 0),
    'opportunity_cost'       => $round($opportunityCost, 0),
    'total_investment'       => $round($totalInvestment, 0),
    'pv_additional_earnings' => $round($pvAdditional, 0),
    'roi_percent'            => round((float)$roiPercent, 2), // keep 2 decimals
];


        return $this->success('Degree ROI calculated successfully.', $data, 200);

    } catch (\Throwable $ex) {
        Log::error('Exception in DegreeRoiController::calculate', [
            'message' => $ex->getMessage(),
            'trace' => $ex->getTraceAsString(),
            'request' => $request->all(),
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'Internal server error while calculating degree ROI. See logs for details.',
            'error' => ['exception' => $ex->getMessage()],
            'data' => null
        ], 500);
    }
}
}
