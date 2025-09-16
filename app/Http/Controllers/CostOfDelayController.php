<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class CostOfDelayController extends Controller
{
    public function calculate(Request $request)
    {
        // Validate input
        $request->validate([
            'monthly_sip'   => 'required|numeric|min:1',
            'term_years'    => 'required|numeric|min:1',
            'annual_return' => 'nullable|numeric|min:0', // optional now
            'delay_months'  => 'required|numeric|min:0',
        ]);

        $monthlySip   = $request->monthly_sip;
        $termYears    = $request->term_years;
        $delayMonths  = $request->delay_months;

        // ✅ Annual return (user → admin → fallback)
        if ($request->filled('annual_return')) {
            $annualReturn = (float) $request->annual_return;
            $rateSource = 'user';
        } else {
            $rateData = Rate::where('calculator', 'commision-calculator')->first();
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $annualReturn = (float) $rateData->settings['loan_rate'];
            } elseif ($rateData && isset($rateData->settings['interest_rate'])) {
                $annualReturn = (float) $rateData->settings['interest_rate'];
            } else {
                $annualReturn = 10; // fallback
            }
            $rateSource = 'admin';
        }

        $months = $termYears * 12;
        $monthlyRate = $annualReturn / 12 / 100;

        // SIP future value formula
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
                'annual_return_source' => $rateSource,
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
