<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TimeValueHourController extends Controller
{
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'annual_salary' => 'required|numeric',
            'hours_per_day' => 'required|numeric',
            'days_per_week' => 'required|numeric',
            'leave_days' => 'required|numeric',
            'public_holidays' => 'required|numeric',
        ]);

        $salary = $validated['annual_salary'];
        $hoursPerDay = $validated['hours_per_day'];
        $daysPerWeek = $validated['days_per_week'];
        $leaveDays = $validated['leave_days'];
        $holidays = $validated['public_holidays'];

        // Weeks in a year
        $weeksPerYear = 52;

        // Total working days
        $workingDays = ($weeksPerYear * $daysPerWeek) - $leaveDays - $holidays;

        // Total working hours
        $workingHours = $workingDays * $hoursPerDay;

        // Hourly value
        $hourlyValue = $salary / $workingHours;

        return response()->json([
            'annual_salary' => $salary,
            'working_hours_per_day' => $hoursPerDay,
            'working_days_per_week' => $daysPerWeek,
            'leave_days' => $leaveDays,
            'public_holidays' => $holidays,
            'total_working_days' => $workingDays,
            'total_working_hours' => $workingHours,
            'hourly_value' => round($hourlyValue, 2)
        ]);
    }
}
