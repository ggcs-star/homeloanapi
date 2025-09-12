<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GratuityCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'basic_pay' => 'required|numeric|min:0',
            'da' => 'required|numeric|min:0',
            'service_years' => 'required|integer|min:0',
            'extra_months' => 'required|integer|min:0|max:11',
        ]);

        $basic = (float)$request->basic_pay;
        $da = (float)$request->da;
        $years = (int)$request->service_years;
        $months = (int)$request->extra_months;

        $salary = $basic + $da;

        // Service rounding rule
        if ($months >= 6) {
            $years += 1;
        }

        // Gratuity formula
        $gratuity = ($salary * 15 * $years) / 26;

        return response()->json([
            'basic_pay' => $basic,
            'da' => $da,
            'last_drawn_salary' => $salary,
            'service_years' => $request->service_years,
            'extra_months' => $months,
            'completed_service' => $years,
            'gratuity_amount' => round($gratuity, 2),
            'message' => "You are eligible for gratuity. Your service of {$request->service_years} year(s) and {$months} month(s) yields a completed service of {$years} year(s)."
        ]);
    }
}
