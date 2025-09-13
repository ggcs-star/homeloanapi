<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SipCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'lumpsum' => 'nullable|numeric|min:0',
            'deposit' => 'required|numeric|min:1',
            'frequency' => 'required|string|in:weekly,monthly,quarterly,half-yearly,yearly',
            'annual_rate' => 'required|numeric|min:1',
            'years' => 'required|numeric|min:1',
        ]);

        $lumpsum = $request->input('lumpsum', 0);
        $P = $request->input('deposit');  
        $r = $request->input('annual_rate') / 100; 
        $t = $request->input('years');

        // Frequency mapping
        $frequencyMap = [
            'weekly' => 52,
            'monthly' => 12,
            'quarterly' => 4,
            'half-yearly' => 2,
            'yearly' => 1,
        ];

        $n = $frequencyMap[strtolower($request->input('frequency'))];

        // Future value of SIP
        $fv_sip = $P * ((pow(1 + $r/$n, $n*$t) - 1) / ($r/$n)) * (1 + $r/$n);

        // Future value of Lumpsum
        $fv_lumpsum = $lumpsum * pow(1 + $r/$n, $n*$t);

        $future_value = $fv_sip + $fv_lumpsum;

        return response()->json([
            'initial_lumpsum' => $lumpsum,
            'regular_deposit' => $P,
            'deposit_frequency' => $request->input('frequency'),
            'annual_rate_percent' => $request->input('annual_rate'),
            'years' => $t,
            'invested_amount' => ($P * $n * $t) + $lumpsum,
            'maturity_value' => round($future_value, 2),
            'total_intrest_earn' => round($future_value - (($P * $n * $t) + $lumpsum), 2),
        ]);
    }
}
