<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FuelCostController extends Controller
{
    /**
     * POST /api/fuel/estimate
     * Returns only the Fuel Cost Estimation table fields.
     */
    public function estimate(Request $request)
    {
        $rules = [
            'one_way_distance_km' => 'required|numeric|min:0',
            'fuel_efficiency_kmpl' => 'required|numeric|min:0.1',
            'fuel_price_per_l' => 'required|numeric|min:0',
            'working_days_per_week' => 'required|integer|min:1|max:7',
            'public_holidays_per_year' => 'nullable|integer|min:0',
            'annual_leave_days' => 'nullable|integer|min:0',
            'work_from_home_days_per_year' => 'nullable|integer|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $p = $request->all();

        // Normalize inputs
        $oneWayKm = floatval($p['one_way_distance_km']);
        $kmpl = floatval($p['fuel_efficiency_kmpl']);
        $pricePerL = floatval($p['fuel_price_per_l']);
        $workingDaysPerWeek = intval($p['working_days_per_week']);
        $publicHolidays = intval($p['public_holidays_per_year'] ?? 0);
        $annualLeave = intval($p['annual_leave_days'] ?? 0);
        $wfhDays = intval($p['work_from_home_days_per_year'] ?? 0);

        // 1) working days estimate (52 weeks)
        $workingDaysYear = $workingDaysPerWeek * 52;

        // 2) effective commute days
        $commuteDays = $workingDaysYear - $publicHolidays - $annualLeave - $wfhDays;
        if ($commuteDays < 0) $commuteDays = 0;

        // 3) daily round-trip km
        $dailyRoundTripKm = $oneWayKm * 2.0;

        // 4) fuel needed per day (round trip) in litres
        $fuelPerDayL = $kmpl > 0 ? ($dailyRoundTripKm / $kmpl) : 0.0;

        // 5) costs
        $dailyFuelCost = $fuelPerDayL * $pricePerL;
        $annualFuelCost = $dailyFuelCost * $commuteDays;
        $monthlyFuelCost = $annualFuelCost / 12.0;

        // round values like UI
        $response = [
            'effective_commute_days_per_year' => round($commuteDays, 0),          // integer-like
            'fuel_needed_per_day_l' => round($fuelPerDayL, 2),                   // litres
            'daily_fuel_cost' => round($dailyFuelCost, 2),                       // currency
            'monthly_fuel_cost' => round($monthlyFuelCost, 2),
            'annual_fuel_cost' => round($annualFuelCost, 2),
        ];

        return response()->json($response);
    }
}
