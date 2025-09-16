<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate;

class Lic_policy_net_interstrate extends Controller
{
    protected function success(string $message, array $data = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'error'   => null,
            'data'    => $data,
        ], $code);
    }

    protected function error(string $message, array $errors = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'error'   => $errors,
            'data'    => null,
        ], $code);
    }

    public function calculate(Request $request): JsonResponse
    {
        try {
            $rules = [
                'premium_amount' => 'required|numeric|min:0.0001',
                'premium_frequency' => 'required|string|in:yearly,monthly',
                'policy_term_years' => 'required|numeric|min:1|max:100',
                'sum_assured' => 'required|numeric|min:0',
                'expected_inflation_percent' => 'sometimes|numeric|min:0|max:100', // ✅ user input optional
                'premium_is_annual' => 'sometimes|boolean',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
            }

            $premiumInput = (float) $request->input('premium_amount');
            $frequency = strtolower($request->input('premium_frequency'));
            $years = (int) $request->input('policy_term_years');
            $sumAssured = (float) $request->input('sum_assured');
            $premiumIsAnnual = (bool) $request->input('premium_is_annual', false);

            // ✅ Inflation Rate Selection (User → DB → Fallback)
            if ($request->filled('expected_inflation_percent')) {
                $inflationPct = (float) $request->input('expected_inflation_percent');
                $rateSource = 'user';
            } else {
                $rateData = Rate::where('calculator', 'commision-calculator')->first();
                if ($rateData && isset($rateData->settings['inflation_rate'])) {
                    $inflationPct = (float) $rateData->settings['inflation_rate'];
                    $rateSource = 'admin';
                } else {
                    $inflationPct = 6.0; // fallback
                    $rateSource = 'fallback';
                }
            }

            $periodsPerYear = $frequency === 'monthly' ? 12 : 1;
            $totalPeriods = $years * $periodsPerYear;

            if ($totalPeriods > 1200) {
                return $this->error('policy_term_years too large (would create huge cashflow).', [], 422);
            }

            $premium = ($premiumIsAnnual && $frequency === 'monthly') ? $premiumInput / $periodsPerYear : $premiumInput;

            $n = max(1, (int)$totalPeriods);
            $cashflows = array_fill(0, $n, -$premium);
            $cashflows[$n - 1] += $sumAssured;
            $totalPremiumsPaid = $premium * $n;

            $periodRate = $this->calculateIRR($cashflows);

            if (!is_finite($periodRate) || is_nan($periodRate)) {
                if ($frequency === 'monthly') {
                    $annualIrrPercent = 0.01;
                    $equivalentSip = 0.01;
                    $note = 'IRR solver failed; fallback set to 0.01% for monthly mode.';
                } else {
                    $annualIrrPercent = 0.00;
                    $equivalentSip = 0.00;
                    $note = 'IRR solver failed; fallback set to 0.00% for yearly mode.';
                }
                Log::warning('IRR solver failed and fallback applied', [
                    'frequency' => $frequency,
                    'premium' => $premium,
                    'periods' => $n,
                    'sumAssured' => $sumAssured,
                ]);
            } else {
                if ($periodRate <= -0.9999999999) {
                    $annualIrrPercent = -100.0;
                } else {
                    $annualRate = pow(1 + $periodRate, $periodsPerYear) - 1;
                    $annualIrrPercent = $annualRate * 100;
                }
                $equivalentSip = $annualIrrPercent;
                $note = null;
            }

            // ✅ PV with chosen inflation
            $pvSumAssured = $years > 0 ? $sumAssured / pow(1 + ($inflationPct / 100), $years) : $sumAssured;

            $data = [
                'annual_interest_rate_irr_percent' => round($annualIrrPercent, 2),
                'present_value_of_sum_assured'    => round($pvSumAssured, 2),
                'total_premiums_paid'             => round($totalPremiumsPaid, 2),
                'sum_assured'                     => round($sumAssured, 2),
                'equivalent_sip_return_rate'      => round($equivalentSip, 2),
                'inflation_rate_percent'          => $inflationPct,
                'inflation_rate_source'           => $rateSource,
            ];

            if ($note) {
                $data['note'] = $note;
            }

            return $this->success('LIC Scheme evaluation completed successfully.', $data, 200);
        } catch (\Throwable $ex) {
            Log::error('Exception in Lic_policy_net_interstrate::calculate', [
                'message' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error while evaluating LIC scheme. See logs for details.',
                'error' => ['exception' => $ex->getMessage()],
                'data' => null
            ], 500);
        }
    }

    private function calculateIRR(array $cashflows, float $guess = 0.01): float
    {
        $maxIterations = 300;
        $tolerance = 1e-9;

        $npvFn = function (float $r) use ($cashflows) {
            $npv = 0.0;
            foreach ($cashflows as $t => $cf) {
                $den = pow(1 + $r, $t + 1);
                if (!is_finite($den) || $den == 0) {
                    return NAN;
                }
                $npv += $cf / $den;
            }
            return $npv;
        };

        $derivFn = function (float $r) use ($cashflows) {
            $deriv = 0.0;
            foreach ($cashflows as $t => $cf) {
                $k = $t + 1;
                $den = pow(1 + $r, $k + 1);
                if (!is_finite($den) || $den == 0) {
                    return NAN;
                }
                $deriv += -$cf * $k / $den;
            }
            return $deriv;
        };

        $rate = $guess;
        for ($i = 0; $i < $maxIterations; $i++) {
            if (!is_finite($rate) || $rate <= -0.9999999999) break;
            $npv = $npvFn($rate);
            $deriv = $derivFn($rate);
            if (!is_finite($npv) || !is_finite($deriv)) break;
            if (abs($npv) < $tolerance) return (float)$rate;
            if (abs($deriv) < 1e-15) break;
            $newRate = $rate - $npv / $deriv;
            if (!is_finite($newRate) || $newRate <= -0.9999999999) break;
            if (abs($newRate - $rate) < $tolerance) return (float)$newRate;
            $rate = $newRate;
        }

        $low = -0.9999999999;
        $high = 10.0;
        $fLow = $npvFn($low + 1e-12);
        $fHigh = $npvFn($high);

        $attempts = 0;
        while (!is_finite($fLow) || !is_finite($fHigh) || $fLow * $fHigh > 0) {
            $high *= 2;
            $fHigh = $npvFn($high);
            $attempts++;
            if ($attempts > 80 || $high > 1e6) {
                return NAN;
            }
        }

        for ($i = 0; $i < 300; $i++) {
            $mid = ($low + $high) / 2;
            $fMid = $npvFn($mid);
            if (!is_finite($fMid)) return NAN;
            if (abs($fMid) < $tolerance) return (float)$mid;
            if ($fLow * $fMid < 0) {
                $high = $mid;
                $fHigh = $fMid;
            } else {
                $low = $mid;
                $fLow = $fMid;
            }
            if (abs($high - $low) < $tolerance) return (float)(($high + $low) / 2);
        }

        return NAN;
    }
}
