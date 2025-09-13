<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportationCostController extends Controller
{
    public function compare(Request $request)
    {
        $rules = [
            // accept either analysis_years or analysis_period_years in request
            'daily_distance_km' => 'required|numeric|min:0',
            'analysis_years' => 'nullable|integer|min:1',
            'analysis_period_years' => 'nullable|integer|min:1',
            'opportunity_cost_percent' => 'required|numeric|min:0',

            'car.car_price' => 'required|numeric|min:0',
            'car.fuel_efficiency_kmpl' => 'required|numeric|min:0',
            'car.fuel_price_per_litre' => 'required|numeric|min:0',
            'car.annual_maintenance' => 'required|numeric|min:0',
            'car.annual_insurance' => 'required|numeric|min:0',
            'car.monthly_parking_fees' => 'required|numeric|min:0',
            'car.depreciation_percent_per_year' => 'required|numeric|min:0',

            'public_transport.monthly_cost' => 'required|numeric|min:0',

            'ride_sharing.cost_per_km' => 'required|numeric|min:0',

            'cycling.cycle_cost' => 'required|numeric|min:0',
            'cycling.annual_maintenance' => 'required|numeric|min:0',
            'cycling.additional_costs' => 'required|numeric|min:0',
            'cycling.lifespan_years' => 'required|integer|min:1',

            // optional method if you want to compare other formulas (annuity/simple)
            'opportunity_method' => 'nullable|in:annuity,simple',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $d = $request->all();

        // allow either key name for years; prefer analysis_years then analysis_period_years
        $years = isset($d['analysis_years']) && $d['analysis_years'] !== null
            ? intval($d['analysis_years'])
            : (isset($d['analysis_period_years']) ? intval($d['analysis_period_years']) : 0);

        if ($years <= 0) {
            return response()->json(['errors' => ['analysis_years' => ['Provide analysis_years or analysis_period_years (integer >=1)']]], 422);
        }

        $oppRate = floatval($d['opportunity_cost_percent']) / 100.0;
        $method = $d['opportunity_method'] ?? 'annuity'; // default annuity

        // annual kms
        $annual_km = floatval($d['daily_distance_km']) * 365.0;

        // --- Car annual components ---
        $annual_fuel_cost = 0.0;
        $fuel_eff = floatval($d['car']['fuel_efficiency_kmpl']);
        if ($fuel_eff > 0) {
            $annual_fuel_liters = $annual_km / $fuel_eff;
            $annual_fuel_cost = $annual_fuel_liters * floatval($d['car']['fuel_price_per_litre']);
        }

        $annual_maintenance = floatval($d['car']['annual_maintenance']);
        $annual_insurance = floatval($d['car']['annual_insurance']);
        $annual_parking = floatval($d['car']['monthly_parking_fees']) * 12.0;

        // **Important change: NO cap on depreciation** — use full percent per year
        $annual_depr = floatval($d['car']['car_price']) * (floatval($d['car']['depreciation_percent_per_year']) / 100.0);

        // annual car cost (sum of annual components)
        $car_annual_cost = $annual_fuel_cost + $annual_maintenance + $annual_insurance + $annual_parking + $annual_depr;

        // --- Public transport (annual) ---
        $public_annual_cost = floatval($d['public_transport']['monthly_cost']) * 12.0;

        // --- Ride sharing (annual) ---
        $ride_annual_cost = $annual_km * floatval($d['ride_sharing']['cost_per_km']);

        // --- Cycling (annual) ---
        $cycle_cost = floatval($d['cycling']['cycle_cost']);
        $cycle_lifespan = max(1, intval($d['cycling']['lifespan_years']));
        $cycle_annual_depr = $cycle_cost / $cycle_lifespan;
        $cycle_annual_cost = $cycle_annual_depr + floatval($d['cycling']['annual_maintenance']) + floatval($d['cycling']['additional_costs']);

        // Prepare annual costs array (rounded)
        $annual_costs = [
            'car_ownership' => round($car_annual_cost, 2),
            'public_transport' => round($public_annual_cost, 2),
            'ride_sharing' => round($ride_annual_cost, 2),
            'cycling' => round($cycle_annual_cost, 2),
        ];

        // cheapest option and saving per year
        $min_annual = min($annual_costs);
        $saving_per_year = $car_annual_cost - $min_annual;

        // compute opportunity benefit according to chosen method
        $benefit = 0.0;
        if ($saving_per_year > 0) {
            if ($method === 'simple' || $oppRate == 0.0) {
                // simple: no compounding
                $benefit = $saving_per_year * $years;
            } else {
                // annuity future value: saving * ( (1+r)^n - 1 ) / r
                $benefit = $saving_per_year * ((pow(1 + $oppRate, $years) - 1.0) / $oppRate);
            }
        }

        // final recommendation string (nicely formatted)
        $cheapest_key = array_keys($annual_costs, $min_annual)[0];
        $niceName = ucwords(str_replace('_', ' ', $cheapest_key));
        $recommendation = "The cheapest transportation option is {$niceName} with an annual cost of Rs. " . number_format($min_annual, 2) . ". Choosing this option over a more expensive alternative (saving ~Rs. " . number_format($saving_per_year, 2) . " per year) could yield a future value of about Rs. " . number_format(round($benefit, 2), 2) . " if invested.";

        // return same structure as UI expects
        return response()->json([
            'analysis_period_years' => $years,
            'results' => [
                'annual_costs' => $annual_costs,
                'opportunity_cost_benefit' => round($benefit, 2),
                'final_recommendation' => $recommendation
            ]
        ]);
    }
}
