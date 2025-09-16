<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

class TimeValueController extends Controller
{
    /**
     * POST /api/emi/inflation
     * Body (json): {
     *   "monthly_emi": 1000000,
     *   "years": 10,
     *   "inflation": 6,
     *   "mode": "yearly"   // "yearly" or "monthly" (optional, default "yearly")
     * }
     */
    public function calculateWithInflation(Request $request)
    {
        try {
            $monthlyEmi = (float) $request->input('monthly_emi', 0);
            $years      = (int) $request->input('years', 0);
            $mode       = strtolower($request->input('mode', 'yearly'));

            if ($monthlyEmi <= 0 || $years <= 0) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'monthly_emi and years must be greater than 0',
                    'error'   => 'Invalid input',
                    'data'    => null
                ], 400);
            }

            // ✅ Inflation fetch (User → DB → Error)
            if ($request->filled('inflation')) {
                $inflation = (float) $request->input('inflation');
                $inflationSource = 'user_input';
            } else {
                $rateData = Rate::where('calculator', 'commision-calculator')->first();
                $adminInflation = $rateData->settings['inflation_rate'] ?? null;

                if ($adminInflation !== null) {
                    $inflation = (float) $adminInflation;
                    $inflationSource = 'db_admin';
                } else {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Inflation rate not provided in request or DB.',
                        'error'   => null,
                        'data'    => null
                    ], 422);
                }
            }

            $totalMonths = $years * 12;
            $totalPaid   = $monthlyEmi * $totalMonths;

            $yearlyBreakdown = [];
            $pvTotal = 0.0;

            if ($mode === 'monthly') {
                $monthlyInflation = $inflation / 12 / 100.0;
                for ($y = 1; $y <= $years; $y++) {
                    $pvYear = 0.0;
                    for ($m = 1; $m <= 12; $m++) {
                        $t = ($y - 1) * 12 + $m;
                        $pvMonth = $monthlyEmi / pow(1 + $monthlyInflation, $t);
                        $pvYear += $pvMonth;
                        $pvTotal += $pvMonth;
                    }
                    $yearlyBreakdown[] = [
                        'year'                => $y,
                        'monthly_emi'         => round($monthlyEmi, 2),
                        'pv_of_year_total'    => round($pvYear, 2),
                        'pv_of_emi_avg_month' => round($pvYear / 12, 2)
                    ];
                }
            } else {
                $annualFactor = 1 + ($inflation / 100.0);
                for ($y = 1; $y <= $years; $y++) {
                    $pvPerMonthInYear = $monthlyEmi / pow($annualFactor, $y);
                    $pvYearTotal = $pvPerMonthInYear * 12;
                    $pvTotal += $pvYearTotal;
                    $yearlyBreakdown[] = [
                        'year'         => $y,
                        'monthly_emi'  => round($monthlyEmi, 2),
                        'pv_of_emi'    => round($pvPerMonthInYear, 2),
                        'pv_of_year_total' => round($pvYearTotal, 2)
                    ];
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'EMI inflation calculation completed successfully',
                'error'   => null,
                'data'    => [
                    'mode'                 => $mode,
                    'monthly_emi'          => round($monthlyEmi, 2),
                    'years'                => $years,
                    'inflation_percent'    => $inflation,
                    'inflation_source'     => $inflationSource,
                    'total_amount_paid'    => round($totalPaid, 2),
                    'pv_of_total_amount'   => round($pvTotal, 2),
                    'yearly_breakdown'     => $yearlyBreakdown
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to calculate EMI with inflation',
                'error'   => $e->getMessage(),
                'data'    => null
            ], 500);
        }
    }
}
