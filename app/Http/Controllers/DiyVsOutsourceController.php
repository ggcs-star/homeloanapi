<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class DiyVsOutsourceController extends Controller
{
    public function calculate(Request $request)
    {
        $v = $request->validate([
            'hourly_value' => 'required|numeric',
            'time_hours' => 'required|numeric',
            'outsource_cost' => 'required|numeric',
            'additional_diy_costs' => 'required|numeric',
            'frequency_per_month' => 'required|integer|min:1',
            'inflation_rate' => 'nullable|numeric|min:0',   // optional now
            'investment_return' => 'nullable|numeric|min:0', // optional now
            'analysis_years' => 'required|integer|min:1',
        ]);

        // ✅ Fetch DB values once
        $rateData = Rate::where('calculator', 'commision-calculator')->first();

        // Inflation rate (user → admin → fallback)
        if ($request->filled('inflation_rate')) {
            $inflation = (float) $request->inflation_rate / 100.0;
            $inflationSource = 'user';
        } else {
            if ($rateData && isset($rateData->settings['inflation_rate'])) {
                $inflation = (float) $rateData->settings['inflation_rate'] / 100.0;
            } else {
                $inflation = 0.10; // fallback 10%
            }
            $inflationSource = 'admin';
        }

        // Investment return (user → admin → fallback)
        if ($request->filled('investment_return')) {
            $annualReturn = (float) $request->investment_return / 100.0;
            $returnSource = 'user';
        } else {
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $annualReturn = (float) $rateData->settings['loan_rate'] / 100.0;
            } else {
                $annualReturn = 0.10; // fallback 10%
            }
            $returnSource = 'admin';
        }

        $hourly         = (float) $v['hourly_value'];
        $hours          = (float) $v['time_hours'];
        $outsourceCost  = (float) $v['outsource_cost'];
        $diyExtra       = (float) $v['additional_diy_costs'];
        $freqPerMonth   = (int) $v['frequency_per_month'];
        $years          = (int) $v['analysis_years'];

        // 1) Current monthly DIY cost
        $time_cost = $hourly * $hours;
        $current_monthly_diy_cost = ($time_cost + $diyExtra) * $freqPerMonth;

        // 2) Current monthly outsource cost
        $current_monthly_outsource_cost = $outsourceCost * $freqPerMonth;

        // 3) Current monthly difference (Outsource - DIY)
        $current_monthly_difference = $current_monthly_outsource_cost - $current_monthly_diy_cost;

        // 4) Future value calculation
        $annual_base = $current_monthly_difference * 12.0;

        $monthly_rate = $annualReturn / 12.0;
        $future_value = 0.0;

        for ($k = 0; $k < $years; $k++) {
            $annual_k = $annual_base * pow(1.0 + $inflation, $k);

            $months_remaining = 12 * ($years - 1 - $k);
            if ($months_remaining <= 0) {
                $fv_contrib = $annual_k;
            } else {
                $fv_contrib = $annual_k * pow(1.0 + $monthly_rate, $months_remaining);
            }

            $future_value += $fv_contrib;
        }

        $current_monthly_diy_cost   = (int) round($current_monthly_diy_cost);
        $current_monthly_outsource_cost = (int) round($current_monthly_outsource_cost);
        $current_monthly_difference = (int) round($current_monthly_difference);
        $future_value = round($future_value, 2);

        return response()->json([
            'current_monthly_diy_cost' => $current_monthly_diy_cost,
            'current_monthly_outsource_cost' => $current_monthly_outsource_cost,
            'current_monthly_difference' => $current_monthly_difference,
            'future_value_monthly_savings' => $future_value,
            'rates_used' => [
                'inflation_rate_percent' => round($inflation * 100, 2),
                'inflation_rate_source'  => $inflationSource,
                'investment_return_percent' => round($annualReturn * 100, 2),
                'investment_return_source'  => $returnSource,
            ]
        ]);
    }
}
