<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class EquivalentRateController extends Controller
{
    public function calculate(Request $request)
    {
        try {
            $years = (int) $request->input('years', 15);
            $type = strtolower($request->input('type', 'loan interest'));

            // ✅ Fetch DB record
            $rateData = Rate::where('calculator', 'commision-calculator')->first();

            // ✅ Rate selection (user → DB loan_rate → fallback)
            if ($request->filled('rate')) {
                $inputRate = (float) $request->input('rate');
                $rateSource = 'user';
            } else {
                if ($rateData && isset($rateData->settings['loan_rate'])) {
                    $inputRate = (float) $rateData->settings['loan_rate'];
                } else {
                    $inputRate = 10.0; // fallback
                }
                $rateSource = 'admin';
            }

            $principal = 1; // only for rate calculation
            $equivalentRate = 0;

            if ($type == 'loan interest') {
                // Loan -> FD/MF
                $monthlyRate = $inputRate / 100 / 12;
                $n = $years * 12;
                $emi = $principal * ($monthlyRate * pow(1 + $monthlyRate, $n)) / (pow(1 + $monthlyRate, $n) - 1);
                $totalPaid = $emi * $n;

                $fdRate = pow($totalPaid / $principal, 1 / $years) - 1;
                $equivalentRate = round($fdRate * 100, 2);

            } elseif ($type == 'fd/mf interest') {
                // FD/MF -> Loan
                $fv = $principal * pow(1 + $inputRate / 100, $years);
                $n = $years * 12;
                $monthlyRate = 0.01; // initial guess
                $tolerance = 0.0000001;

                for ($i = 0; $i < 10000; $i++) {
                    $emi = $principal * ($monthlyRate * pow(1 + $monthlyRate, $n)) / (pow(1 + $monthlyRate, $n) - 1);
                    $total = $emi * $n;
                    $diff = $total - $fv;
                    if (abs($diff) < $tolerance) break;
                    $monthlyRate = $monthlyRate - $diff / ($n * $principal);
                }

                $equivalentRate = round($monthlyRate * 12 * 100, 2);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid type selected. Please choose "loan interest" or "fd/mf interest".',
                    'error' => null,
                    'data' => null
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Equivalent rate calculated successfully',
                'error' => null,
                'data' => [
                    'given_type' => $type,
                    'input_rate_percent' => $inputRate,
                    'input_rate_source' => $rateSource,
                    'years' => $years,
                    'equivalent_rate_percent' => $equivalentRate
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate equivalent rate',
                'error' => null,
                'data' => null
            ], 500);
        }
    }
}
