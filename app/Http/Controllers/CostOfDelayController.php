<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CostOfDelayController extends Controller
{
    public function calculate(Request $request)
    {
        // Validate input
        $request->validate([
            'monthly_sip'   => 'required|numeric|min:1',
            'term_years'    => 'required|numeric|min:1',
            'annual_return' => 'required|numeric|min:1',
            'delay_months'  => 'required|numeric|min:0',
        ]);

        $monthlySip   = $request->monthly_sip;
        $termYears    = $request->term_years;
        $annualReturn = $request->annual_return;
        $delayMonths  = $request->delay_months;

        $months = $termYears * 12;
        $monthlyRate = $annualReturn / 12 / 100;

        // SIP future value formula (without the extra * (1 + monthlyRate))
        $futureValue = $monthlySip * ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);

        $monthsDelayed = $months - $delayMonths;
        $futureValueDelayed = $monthsDelayed > 0
            ? $monthlySip * ((pow(1 + $monthlyRate, $monthsDelayed) - 1) / $monthlyRate)
            : 0;

        $loss = $futureValue - $futureValueDelayed;
        $lossPercent = $futureValue > 0 ? ($loss / $futureValue) * 100 : 0;

        return response()->json([
            'input' => [
                'monthly_sip'   => $monthlySip,
                'term_years'    => $termYears,
                'annual_return' => $annualReturn,
                'delay_months'  => $delayMonths,
            ],
            'results' => [
                'start_today'   => round($futureValue, 2),
                'delayed_start' => round($futureValueDelayed, 2),
                'loss_amount'   => round($loss, 2),
                'loss_percent'  => round($lossPercent, 2),
            ]
        ]);
    }
}
