<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
            'interest_rate' => 'required|numeric|min:0',
            'term_length' => 'required|numeric|min:0.01',
            'term_type' => 'required|string|in:years,months',
            'compounding_frequency' => 'required|integer|in:1,2,4,12',
            'deposit_at' => 'nullable|string|in:start,end',
            'skip_first_deposit' => 'nullable|boolean'
        ]);

        $P = (float)$v['lump_sum'];
        $R = (float)$v['regular_deposit'];
        $d = (int)$v['deposit_frequency'];        // deposits per year
        $r = (float)$v['interest_rate'] / 100.0;  // annual rate decimal
        $n = (int)$v['compounding_frequency'];    // compounding per year
        $termLen = (float)$v['term_length'];
        $termType = $v['term_type'];

        $depositAt = $v['deposit_at'] ?? 'start';
        // default false so first deposit is counted unless user explicitly asks to skip
        $skipFirst = $v['skip_first_deposit'] ?? false;

        // convert term to years (support months)
        $t = ($termType === 'months') ? ($termLen / 12.0) : $termLen;
        $eps = 1e-9;

        // --- integer-step schedule approach ---
        // steps per year = LCM(deposit_freq, compounding_freq)
        $stepsPerYear = $this->lcm($d, $n);

        // interval (in steps) between deposits
        $depositInterval = (int)($stepsPerYear / $d); // integer by LCM
        // total steps across whole term
        $totalSteps = (int) round($t * $stepsPerYear);

        // build deposit steps (1-based step indices). Use integer arithmetic to avoid float issues.
        $scheduled_count = (int) floor($t * $d + $eps); // total scheduled deposits (before skip)
        $depositSteps = []; // map of step -> number of deposits in that step (should be 0/1 usually)
        // starting k index: if skipFirst true we start from k=1 else k=0
        $kStart = ($skipFirst && $scheduled_count > 0) ? 1 : 0;
        for ($k = $kStart; $k < $scheduled_count; $k++) {
            // deposit step index:
            $stepIndex = 1 + $k * $depositInterval; // 1-based
            if ($stepIndex <= $totalSteps) {
                if (!isset($depositSteps[$stepIndex])) $depositSteps[$stepIndex] = 0;
                $depositSteps[$stepIndex] += 1;
            }
        }

        // per-step multiplier so that after stepsPerYear steps we get (1 + r/n)^n overall for the year
        $perStepMultiplier = pow(1.0 + ($r / $n), ($n / $stepsPerYear));

        // simulate step-by-step
        $balance = $P;
        $totalDeposit = $P;
        $yearWise = [];

        for ($step = 1; $step <= $totalSteps; $step++) {
            $countThisStep = isset($depositSteps[$step]) ? $depositSteps[$step] : 0;
            $depositAmount = $countThisStep * $R;

            if ($depositAt === 'start' && $depositAmount > 0) {
                $balance += $depositAmount;
                $totalDeposit += $depositAmount;
            }

            // apply interest for this step
            $balance *= $perStepMultiplier;

            if ($depositAt === 'end' && $depositAmount > 0) {
                $balance += $depositAmount;
                $totalDeposit += $depositAmount;
            }

            // snapshot at end of year or final step
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

        // defensive: any leftover scheduled deposits (shouldn't happen) add them
        // (but with above logic this won't normally occur)
        // compute final results
        $maturity = round($balance, 2);
        $totalInterest = round($maturity - $totalDeposit, 2);

        return response()->json([
            'maturity_amount' => $maturity,
            'total_deposit' => round($totalDeposit, 2),
            'total_interest' => $totalInterest,
            'year_wise' => $yearWise
        ]);
    }
}
