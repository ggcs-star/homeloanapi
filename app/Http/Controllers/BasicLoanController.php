<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BasicLoanController extends Controller
{
    public function store(Request $request)
    {
        $v = $request->validate([
            'loan_amount' => 'required|numeric|min:1',
            'annual_interest_rate' => 'required|numeric|min:0',
            'loan_term_years' => 'required|integer|min:1'
        ]);

        $P = (float)$v['loan_amount'];
        $annualRate = (float)$v['annual_interest_rate'];
        $years = (int)$v['loan_term_years'];

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
            'loan_term_years' => $years,
            'emi' => round($emi, 2),
            'total_payment' => round($totalPayment, 2),
            'total_interest' => round($totalInterest, 2)
        ]);
    }
}
