<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TaxSavingsCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        // Validation
        $request->validate([
            'investment_amount'        => 'required|numeric|min:0',
            'term_years'               => 'required|numeric|min:1',
            'annual_return_tax_saving' => 'required|numeric|min:0',
            'income_tax_slab'          => 'required|numeric|min:0|max:100',
            'annual_return_regular'    => 'required|numeric|min:0',
            'tax_on_gains'             => 'required|numeric|min:0|max:100',
        ]);

        $investment = $request->investment_amount;
        $years = $request->term_years;
        $r_tax = $request->annual_return_tax_saving / 100;
        $income_tax = $request->income_tax_slab / 100;
        $r_regular = $request->annual_return_regular / 100;
        $tax_gains = $request->tax_on_gains / 100;

        // -----------------------------
        // Tax-saving investment
        // -----------------------------
        $immediate_tax_savings = $investment * $income_tax;
        $effective_investment = $investment - $immediate_tax_savings; // out-of-pocket

        // Future value (tax-saving)
        $fv_tax_saving = $investment * pow(1 + $r_tax, $years);
        $net_gain_tax_saving = $fv_tax_saving - $effective_investment;

        // -----------------------------
        // Regular investment
        // -----------------------------
        $fv_regular_before_tax = $investment * pow(1 + $r_regular, $years); // matches reference FV
        $gains_regular = $fv_regular_before_tax - $investment;
        $tax_on_regular = $gains_regular * $tax_gains;
        $net_gain_regular = $gains_regular - $tax_on_regular;
        $fv_regular_after_tax = $investment + $net_gain_regular;

        // -----------------------------
        // Decision
        // -----------------------------
        $decision = $net_gain_tax_saving >= $net_gain_regular
            ? 'Tax-saving investment is better.'
            : 'Regular investment is better.';

        // -----------------------------
        // Response
        // -----------------------------
        return response()->json([
            'input' => [
                'investment_amount' => $investment,
                'term_years' => $years,
                'annual_return_tax_saving' => $request->annual_return_tax_saving,
                'income_tax_slab' => $request->income_tax_slab,
                'annual_return_regular' => $request->annual_return_regular,
                'tax_on_gains' => $request->tax_on_gains,
            ],
            'results' => [
                'tax_saving_investment' => [
                    'investment_amount' => round($effective_investment, 2),
                    'immediate_tax_savings' => round($immediate_tax_savings, 2),
                    'future_value' => round($fv_tax_saving, 2),
                    'net_gain' => round($net_gain_tax_saving, 2),
                ],
                'regular_investment' => [
                    'future_value_before_tax' => round($fv_regular_before_tax, 2), // added
                    // // 'future_value_after_tax' => round($fv_regular_after_tax, 2),
                    // 'tax_on_gains' => round($tax_on_regular, 2),
                    'net_gain' => round($net_gain_regular, 2),
                ],
                'decision' => $decision
            ]
        ]);
    }
}
