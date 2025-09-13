<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
            // Get inputs (default values included)
            $monthlyEmi = (float) ($request->input('monthly_emi') ?? 10000);
            $inflationRate = (float) ($request->input('inflation_rate') ?? 5);
            $monthlyRent = (float) ($request->input('monthly_rent') ?? 15000);
            $expectedRentIncrement = (float) ($request->input('expected_rent_increment') ?? 3);
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

                // Accumulate totals
                $totalEmi += $currentEmi;
                $totalRent += $currentRent;

                // PV calculation (discounting using inflation)
                $pvEmi += $currentEmi / pow(1 + $monthlyInflationRate, $month);
                $pvRent += $currentRent / pow(1 + $monthlyInflationRate, $month);

                // Store yearly projection
                if ($month % 12 === 0) {
                    $year = $month / 12;
                    $projection[] = [
                        'year' => $year,
                        'monthly_emi' => round($currentEmi, 2),
                        'monthly_rent' => round($currentRent, 2)
                    ];
                }

                // Increase EMI and Rent for next month
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
                        'inflation_rate' => $inflationRate,
                        'monthly_rent' => $monthlyRent,
                        'expected_rent_increment' => $expectedRentIncrement,
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
