<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class EmiVsRentController extends Controller
{
    /**
     * EMI vs Rent Projection API
     *
     * Works with POST (JSON) or GET (query params)
     */
    public function emiVsRentProjection(Request $request)
    {
        try {
            // Fetch DB rates
            $rateData = Rate::where('calculator', 'commision-calculator')->first();

            // Monthly EMI (always user/default)
            $monthlyEmi = (float) ($request->input('monthly_emi') ?? 10000);

            // Inflation rate (user → admin → fallback)
            if ($request->filled('inflation_rate')) {
                $inflationRate = (float) $request->input('inflation_rate');
                $inflationSource = 'user';
            } else {
                if ($rateData && isset($rateData->settings['inflation_rate'])) {
                    $inflationRate = (float) $rateData->settings['inflation_rate'];
                } else {
                    $inflationRate = 5.0; // fallback
                }
                $inflationSource = 'admin';
            }

            // Rent increment (user → admin → fallback)
            if ($request->filled('expected_rent_increment')) {
                $expectedRentIncrement = (float) $request->input('expected_rent_increment');
                $rentIncrementSource = 'user';
            } else {
                if ($rateData && isset($rateData->settings['loan_rate'])) {
                    $expectedRentIncrement = (float) $rateData->settings['loan_rate'];
                } else {
                    $expectedRentIncrement = 3.0; // fallback
                }
                $rentIncrementSource = 'admin';
            }

            // Monthly Rent (always user/default)
            $monthlyRent = (float) ($request->input('monthly_rent') ?? 15000);

            // Analysis period (always user/default)
            $analysisPeriod = (int) ($request->input('analysis_period') ?? 5);

            $months = $analysisPeriod * 12;
            $monthlyInflationRate = $inflationRate / 12 / 100;
            $monthlyRentIncrementRate = $expectedRentIncrement / 12 / 100;

            $projection = [];
            $totalEmi = 0;
            $totalRent = 0;
            $pvEmi = 0;
            $pvRent = 0;

            $currentEmi = $monthlyEmi;
            $currentRent = $monthlyRent;

            for ($month = 1; $month <= $months; $month++) {
                // Totals
                $totalEmi += $currentEmi;
                $totalRent += $currentRent;

                // PV calculation
                $pvEmi += $currentEmi / pow(1 + $monthlyInflationRate, $month);
                $pvRent += $currentRent / pow(1 + $monthlyInflationRate, $month);

                // Yearly snapshot
                if ($month % 12 === 0) {
                    $year = $month / 12;
                    $projection[] = [
                        'year' => $year,
                        'monthly_emi' => round($currentEmi, 2),
                        'monthly_rent' => round($currentRent, 2)
                    ];
                }

                // Growth
                $currentEmi *= 1 + $monthlyInflationRate;
                $currentRent *= 1 + $monthlyRentIncrementRate;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'EMI vs Rent projection calculated successfully',
                'error' => null,
                'data' => [
                    'input' => [
                        'monthly_emi' => $monthlyEmi,
                        'inflation_rate_percent' => $inflationRate,
                        'inflation_rate_source' => $inflationSource,
                        'monthly_rent' => $monthlyRent,
                        'expected_rent_increment_percent' => $expectedRentIncrement,
                        'rent_increment_source' => $rentIncrementSource,
                        'analysis_period_years' => $analysisPeriod
                    ],
                    'projection' => $projection,
                    'total_emi_paid' => round($totalEmi, 2),
                    'total_rent_paid' => round($totalRent, 2),
                    'pv_of_all_emi' => round($pvEmi, 2),
                    'pv_of_all_rent' => round($pvRent, 2)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate EMI vs Rent projection',
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
