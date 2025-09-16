<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class BasicLoanController extends Controller
{
    public function calculate(Request $request)
    {
        $v = $request->validate([
            'loan_amount' => 'required|numeric|min:1',
            'loan_term_years' => 'required|integer|min:1',
            'annual_interest_rate' => 'nullable|numeric|min:0'
        ]);

        $P = (float) $v['loan_amount'];
        $years = (int) $v['loan_term_years'];

        // ✅ Step 1: User input
        if ($request->filled('annual_interest_rate')) {
            $annualRate = (float) $request->input('annual_interest_rate');
            $rateSource = 'user';
        } else {
         // ✅ Step 2: Admin default from DB
$rateData = Rate::where('calculator', 'commision-calculator')->first();

if ($rateData) {
    if (isset($rateData->settings['loan_rate'])) {
        $annualRate = (float) $rateData->settings['loan_rate'];
    } elseif (isset($rateData->settings['interest_rate'])) {
        $annualRate = (float) $rateData->settings['interest_rate'];
    } else {
        $annualRate = 10; // fallback
    }
    $rateSource = 'admin';
} else {
    $annualRate = 10; // fallback if no DB record found
    $rateSource = 'admin';
}
        }
        // ✅ EMI Calculation
        $monthlyRate = $annualRate / 12 / 100;
        $n = $years * 12;

        if ($monthlyRate == 0) {
            $emi = $P / $n;
        } else {
            $emi = $P * $monthlyRate * pow(1 + $monthlyRate, $n) / (pow(1 + $monthlyRate, $n) - 1);
        }

        $totalPayment = $emi * $n;
        $totalInterest = $totalPayment - $P;

        return response()->json([
            'loan_amount' => round($P, 2),
            'annual_interest_rate' => round($annualRate, 2),
            'rate_source' => $rateSource,
            'loan_term_years' => $years,
            'emi' => round($emi, 2),
            'total_payment' => round($totalPayment, 2),
            'total_interest' => round($totalInterest, 2)
        ]);
    }
}
