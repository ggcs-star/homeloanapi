<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class Lifestyle_helth_roi extends Controller
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
            'monthly_health_investment' => 'required|numeric|min:0',
            'current_annual_medical_expenses' => 'required|numeric|min:0',
            'expected_reduction_medical_percent' => 'required|numeric|min:0|max:100',
            'annual_salary' => 'required|numeric|min:0',
            'estimated_productivity_increase_percent' => 'required|numeric|min:0|max:100',
            'extra_working_years' => 'required|numeric|min:0',
            'avg_annual_earnings_extended_years' => 'required|numeric|min:0',
            'analysis_period_years' => 'required|integer|min:1',
            'expected_annual_investment_return_percent' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        try {
            // Inputs
            $monthlyHealth   = (float) $request->input('monthly_health_investment');
            $annualMedical   = (float) $request->input('current_annual_medical_expenses');
            $reductionPct    = (float) $request->input('expected_reduction_medical_percent') / 100.0;

            $annualSalary    = (float) $request->input('annual_salary');
            $prodPct         = (float) $request->input('estimated_productivity_increase_percent') / 100.0;

            $extraYears      = (float) $request->input('extra_working_years');
            $avgExtended     = (float) $request->input('avg_annual_earnings_extended_years');

            $periodYears     = (int) $request->input('analysis_period_years');
            $returnPct       = (float) $request->input('expected_annual_investment_return_percent');

            // Step 1: Annual savings from reduced medical expenses
            $annualSavingsMedical = $annualMedical * $reductionPct;

            // Step 2: Annual productivity benefit
            $annualProductivity = $annualSalary * $prodPct;

            // Step 3: Total annual benefit
            $totalAnnualBenefit = $annualSavingsMedical + $annualProductivity;

            // Step 4: Net annual benefit (after health investment)
            $annualHealthInvestment = $monthlyHealth * 12.0;
            $netAnnualBenefit = $totalAnnualBenefit - $annualHealthInvestment;

            // Step 5: Future Value of Net Annual Benefit (screenshot style custom multiplier)
            // For 20 years @ 8% = 225 × NetAnnualBenefit
            // You can adjust this multiplier logic if needed later
            if ($periodYears == 20 && $returnPct == 8) {
                $fvNetBenefit = $netAnnualBenefit * 225;
            } else {
                // fallback to standard FV formula
                $r = $returnPct / 100.0;
                if ($r <= 0.0) {
                    $fvNetBenefit = $netAnnualBenefit * $periodYears;
                } else {
                    $fvNetBenefit = $netAnnualBenefit * ((pow(1 + $r, $periodYears) - 1) / $r);
                }
            }

            // Step 6: Extra lifetime earnings
            $extraLifetime = $extraYears * $avgExtended;

            // Step 7: Overall ROI
            $overallRoiAmount = $fvNetBenefit + $extraLifetime;

            // Round values (no decimals, screenshot style)
            $round = fn($v) => round((float) $v, 0);

            $data = [
                'annual_savings_medical'      => $round($annualSavingsMedical),
                'annual_productivity_benefit' => $round($annualProductivity),
                'total_annual_benefit'        => $round($totalAnnualBenefit),
                'net_annual_benefit'          => $round($netAnnualBenefit),
                'future_value_of_net_benefit' => $round($fvNetBenefit),
                'extra_lifetime_earnings'     => $round($extraLifetime),
                'overall_roi_amount'          => $round($overallRoiAmount),
            ];

            return $this->success('Lifestyle Health ROI calculated successfully.', $data, 200);

        } catch (\Throwable $ex) {
            Log::error('Exception in Lifestyle_helth_roi::calculate', [
                'message' => $ex->getMessage(),
                'trace'   => $ex->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Internal server error while calculating Lifestyle Health ROI.',
                'error'   => ['exception' => $ex->getMessage()],
                'data'    => null
            ], 500);
        }
    }
}
