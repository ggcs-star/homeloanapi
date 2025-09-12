<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FireCalcDebugController extends Controller
{
    public function calculate(Request $request)
    {
        $v = $request->validate([
            'current_expenses' => 'required|numeric',
            'inflation' => 'required|numeric',
            'withdrawal_rate' => 'required|numeric',
            'current_savings' => 'required|numeric',
            'annual_income' => 'required|numeric',
            'savings_rate' => 'required|numeric',
            'return_pre_retirement' => 'required|numeric',
            'return_post_retirement' => 'required|numeric',
            'current_age' => 'required|integer',
            'desired_retirement_age' => 'required|integer',
            'life_expectancy' => 'required|integer',
        ]);

        // Inputs
        $expenses       = (float) $v['current_expenses'];
        $inflation      = (float) $v['inflation'] / 100.0;
        $withdrawalRate = (float) $v['withdrawal_rate'] / 100.0;
        $savings        = (float) $v['current_savings'];
        $income         = (float) $v['annual_income'];
        $saveRate       = (float) $v['savings_rate'] / 100.0;
        $preReturn      = (float) $v['return_pre_retirement'] / 100.0;
        $postReturn     = (float) $v['return_post_retirement'] / 100.0;
        $age            = (int) $v['current_age'];
        $retAge         = (int) $v['desired_retirement_age'];
        $life           = (int) $v['life_expectancy'];

        $yearsToRetire   = $retAge - $age;
        $retirementYears = $life - $retAge;

        // --- Debug calculations ---
        $expensesAtRetirementRaw = $expenses * pow(1 + $inflation, $yearsToRetire);
        $expensesAtRetirementRounded = round($expensesAtRetirementRaw);

        $firstYearWithdrawalRaw = $expensesAtRetirementRaw * (1 + $withdrawalRate);
        $firstYearWithdrawalRounded = round($firstYearWithdrawalRaw);

        $postRealFisher = (($postReturn + 1) / (1 + $inflation)) - 1;
        $postRealSubtract = $postReturn - $inflation;

        $annuityFisher = (1 - pow(1 + $postRealFisher, -$retirementYears)) / $postRealFisher;
        $annuitySubtract = (1 - pow(1 + $postRealSubtract, -$retirementYears)) / $postRealSubtract;

        $targetCorpusFisher = $firstYearWithdrawalRounded * $annuityFisher;
        $targetCorpusSubtract = $firstYearWithdrawalRounded * $annuitySubtract;

        // Yearly deposit simulation
        $annualSavings = $income * $saveRate;
        $bal = $savings;
        $yrs = 0;
        while ($bal < $targetCorpusSubtract && $yrs < 200) {
            $bal = $bal * (1 + $preReturn) + $annualSavings;
            $yrs++;
        }

        return response()->json([
            'inputs' => $v,
            'yearsToRetire' => $yearsToRetire,
            'retirementYears' => $retirementYears,

            'expensesAtRetirementRaw' => $expensesAtRetirementRaw,
            'expensesAtRetirementRounded' => $expensesAtRetirementRounded,

            'firstYearWithdrawalRaw' => $firstYearWithdrawalRaw,
            'firstYearWithdrawalRounded' => $firstYearWithdrawalRounded,

            'postRealFisher' => $postRealFisher,
            'postRealSubtract' => $postRealSubtract,

            'annuityFisher' => $annuityFisher,
            'annuitySubtract' => $annuitySubtract,

            'targetCorpusFisher' => $targetCorpusFisher,
            'targetCorpusSubtract' => $targetCorpusSubtract,

            'yearsNeededByYearlyDeposit' => $yrs,
        ]);
    }
}
