<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
            'inflation_rate' => 'required|numeric',
            'investment_return' => 'required|numeric',
            'analysis_years' => 'required|integer|min:1',
        ]);

        $hourly         = (float) $v['hourly_value'];
        $hours          = (float) $v['time_hours'];
        $outsourceCost  = (float) $v['outsource_cost'];
        $diyExtra       = (float) $v['additional_diy_costs'];
        $freqPerMonth   = (int) $v['frequency_per_month'];
        $inflation      = (float) $v['inflation_rate'] / 100.0;   // annual inflation
        $annualReturn   = (float) $v['investment_return'] / 100.0; // annual return
        $years          = (int) $v['analysis_years'];

        // 1) Current monthly DIY cost = (hourly_value * time_hours + additional_diy_costs) * frequency
        $time_cost = $hourly * $hours;
        $current_monthly_diy_cost = ($time_cost + $diyExtra) * $freqPerMonth;

        // 2) Current monthly outsource cost
        $current_monthly_outsource_cost = $outsourceCost * $freqPerMonth;

        // 3) Current monthly difference (Outsource - DIY)
        $current_monthly_difference = $current_monthly_outsource_cost - $current_monthly_diy_cost;

        // 4) Future value calculation used by the app:
        // - convert monthly difference to annual base
        // - for each year k = 0..years-1: escalate by inflation: annual_k = annual_base * (1+inflation)^k
        // - deposit each annual_k at end of year k and grow it to the end using monthly compounding at annualReturn
        $annual_base = $current_monthly_difference * 12.0;

        $monthly_rate = $annualReturn / 12.0;
        $future_value = 0.0;

        for ($k = 0; $k < $years; $k++) {
            // amount deposited at end of year k (year index starting at 0)
            $annual_k = $annual_base * pow(1.0 + $inflation, $k);

            // months remaining from deposit at end of year k to analysis end:
            $months_remaining = 12 * ($years - 1 - $k);
            if ($months_remaining <= 0) {
                // deposit made in last year -> no growth (or growth for 0 months)
                $fv_contrib = $annual_k;
            } else {
                $fv_contrib = $annual_k * pow(1.0 + $monthly_rate, $months_remaining);
            }

            $future_value += $fv_contrib;
        }

        // Format outputs: integer for monthly numbers, two decimals for future value to match app
        $current_monthly_diy_cost   = (int) round($current_monthly_diy_cost);
        $current_monthly_outsource_cost = (int) round($current_monthly_outsource_cost);
        $current_monthly_difference = (int) round($current_monthly_difference);
        $future_value = round($future_value, 2);

        return response()->json([
            'current_monthly_diy_cost' => $current_monthly_diy_cost,
            'current_monthly_outsource_cost' => $current_monthly_outsource_cost,
            'current_monthly_difference' => $current_monthly_difference,
            'future_value_monthly_savings' => $future_value
        ]);
    }
}
