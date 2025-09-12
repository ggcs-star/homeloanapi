<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BudgetPlannerController extends Controller
{
    public function calculate(Request $request)
    {
        // --- Inputs ---
        $income = (float) $request->input('monthly_income', 0);

        // Fixed Expenses
        $rent = (float) $request->input('rent', 0);
        $utilities = (float) $request->input('utilities', 0);
        $loans = (float) $request->input('loans', 0);
        $insurance = (float) $request->input('insurance', 0);
        $subscriptions = (float) $request->input('subscriptions', 0);

        // Variable Expenses
        $groceries = (float) $request->input('groceries', 0);
        $transport = (float) $request->input('transport', 0);
        $dining = (float) $request->input('dining', 0);
        $shopping = (float) $request->input('shopping', 0);
        $medical = (float) $request->input('medical', 0);
        $education = (float) $request->input('education', 0);
        $misc = (float) $request->input('miscellaneous', 0);

        // Goals
        $savings_goal = (float) $request->input('savings_goal', 0);
        $emergency_fund = (float) $request->input('emergency_fund', 0);

        // --- Calculations ---
        $total_fixed = $rent + $utilities + $loans + $insurance + $subscriptions;
        $total_variable = $groceries + $transport + $dining + $shopping + $medical + $education + $misc;

        $total_expenses = $total_fixed + $total_variable + $emergency_fund;
        $total_savings = $income - $total_expenses;
        $savings_percent = ($income > 0) ? ($total_savings / $income) * 100 : 0;

        // Percentages
        $fixed_percent = ($income > 0) ? ($total_fixed / $income) * 100 : 0;
        $variable_percent = ($income > 0) ? ($total_variable / $income) * 100 : 0;
        $emergency_percent = ($income > 0) ? ($emergency_fund / $income) * 100 : 0;

        // --- Response ---
        return response()->json([
            'results' => [
                'monthly_income' => (int) $income,
                'total_fixed_expenses' => (int) $total_fixed,
                'total_variable_expenses' => (int) $total_variable,
                'emergency_fund' => (int) $emergency_fund,
                'total_expenses' => (int) $total_expenses,
                'total_savings' => (int) $total_savings,
                'savings_percent' => round($savings_percent, 2)
            ],
            'explanation' => [
                'fixed_percent' => round($fixed_percent, 1),
                'variable_percent' => round($variable_percent, 1),
                'emergency_percent' => round($emergency_percent, 1),
                'remaining_percent' => round(100 - ($fixed_percent + $variable_percent + $emergency_percent), 1)
            ]
        ]);
    }
}
