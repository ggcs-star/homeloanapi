<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class CompoundInterestController extends Controller
{
    private function gcd($a, $b) {
        return $b == 0 ? $a : $this->gcd($b, $a % $b);
    }

    private function lcm($a, $b) {
        return (int)(($a * $b) / $this->gcd($a, $b));
    }

    public function calculate(Request $request)
    {
        $v = $request->validate([
            'lump_sum' => 'required|numeric|min:0',
            'regular_deposit' => 'required|numeric|min:0',
            'deposit_frequency' => 'required|integer|in:1,2,4,12',
            'interest_rate' => 'nullable|numeric|min:0', // optional
            'term_length' => 'required|numeric|min:0.01',
            'term_type' => 'required|string|in:years,months',
            'compounding_frequency' => 'required|integer|in:1,2,4,12',
            'deposit_at' => 'nullable|string|in:start,end',
            'skip_first_deposit' => 'nullable|boolean'
        ]);

        // ✅ Interest Rate (user → admin → fallback)
        if ($request->filled('interest_rate')) {
            $interestRate = (float) $request->input('interest_rate');
            $interestRateSource = 'user';
        } else {
            $rateData = Rate::where('calculator', 'commision-calculator')->first();
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $interestRate = (float) $rateData->settings['loan_rate'];
            } elseif ($rateData && isset($rateData->settings['interest_rate'])) {
                $interestRate = (float) $rateData->settings['interest_rate'];
            } else {
                $interestRate = 10; // fallback
            }
            $interestRateSource = 'admin';
        }

        $P = (float)$v['lump_sum'];
        $R = (float)$v['regular_deposit'];
        $d = (int)$v['deposit_frequency'];        // deposits per year
        $r = (float)$interestRate / 100.0;        // annual rate decimal
        $n = (int)$v['compounding_frequency'];    // compounding per year
        $termLen = (float)$v['term_length'];
        $termType = $v['term_type'];

        $depositAt = $v['deposit_at'] ?? 'start';
        $skipFirst = $v['skip_first_deposit'] ?? false;

        // convert term to years
        $t = ($termType === 'months') ? ($termLen / 12.0) : $termLen;
        $eps = 1e-9;

        $stepsPerYear = $this->lcm($d, $n);
        $depositInterval = (int)($stepsPerYear / $d);
        $totalSteps = (int) round($t * $stepsPerYear);

        $scheduled_count = (int) floor($t * $d + $eps);
        $depositSteps = [];
        $kStart = ($skipFirst && $scheduled_count > 0) ? 1 : 0;

        for ($k = $kStart; $k < $scheduled_count; $k++) {
            $stepIndex = 1 + $k * $depositInterval;
            if ($stepIndex <= $totalSteps) {
                if (!isset($depositSteps[$stepIndex])) $depositSteps[$stepIndex] = 0;
                $depositSteps[$stepIndex] += 1;
            }
        }

        $perStepMultiplier = pow(1.0 + ($r / $n), ($n / $stepsPerYear));

        $balance = $P;
        $totalDeposit = $P;
        $yearWise = [];

        for ($step = 1; $step <= $totalSteps; $step++) {
            $countThisStep = $depositSteps[$step] ?? 0;
            $depositAmount = $countThisStep * $R;

            if ($depositAt === 'start' && $depositAmount > 0) {
                $balance += $depositAmount;
                $totalDeposit += $depositAmount;
            }

            $balance *= $perStepMultiplier;

            if ($depositAt === 'end' && $depositAmount > 0) {
                $balance += $depositAmount;
                $totalDeposit += $depositAmount;
            }

            if (($step % $stepsPerYear) == 0 || $step == $totalSteps) {
                $yearsElapsed = $step / $stepsPerYear;
                $yearWise[] = [
                    'year' => round(min($t, $yearsElapsed), 6),
                    'total_deposit_so_far' => round($totalDeposit, 2),
                    'total_interest_so_far' => round($balance - $totalDeposit, 2),
                    'balance_end_of_year' => round($balance, 2),
                ];
            }
        }

        $maturity = round($balance, 2);
        $totalInterest = round($maturity - $totalDeposit, 2);

        return response()->json([
            'maturity_amount' => $maturity,
            'total_deposit'   => round($totalDeposit, 2),
            'total_interest'  => $totalInterest,
            'year_wise'       => $yearWise,
            'rates_used' => [
                'interest_rate'        => $interestRate,
                'interest_rate_source' => $interestRateSource
            ]
        ]);
    }
}
