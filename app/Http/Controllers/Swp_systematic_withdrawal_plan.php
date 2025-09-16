<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate; // ✅ DB से fetch
use Throwable;
use Illuminate\Support\Facades\Log;

class Swp_systematic_withdrawal_plan extends Controller
{
    protected function success(string $message, array $data = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'error' => null,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message, array $errorPayload = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'error' => $errorPayload,
            'data' => null,
        ], $code);
    }

    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'lump_sum_deposit' => 'required|numeric|min:0',
            'regular_withdrawal' => 'required|numeric|min:0',
            'withdrawal_frequency' => 'required|string|in:monthly,quarterly,half-yearly,yearly',
            'annual_withdrawal_adjustment' => 'nullable|numeric',
            'adjustment_type' => 'required|string|in:percent,rupee',
            'expected_annual_return' => 'sometimes|numeric|min:0', // ✅ optional
            'withdrawal_term' => 'required|numeric|min:0',
            'term_unit' => 'required|string|in:years,months',
            'include_year_wise' => 'nullable|boolean',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        try {
            // Inputs
            $principal = (float) $request->input('lump_sum_deposit');
            $withdrawal = (float) $request->input('regular_withdrawal');
            $freq = $request->input('withdrawal_frequency');
            $annualAdj = (float) $request->input('annual_withdrawal_adjustment', 0);
            $adjType = $request->input('adjustment_type');
            $termValue = (float) $request->input('withdrawal_term');
            $termUnit = $request->input('term_unit');
            $includeYearWise = (bool) $request->input('include_year_wise', true);

            // ✅ Annual Return (User → DB → Error)
            if ($request->filled('expected_annual_return')) {
                $annualReturn = (float) $request->input('expected_annual_return');
                $rateSource = 'user_input';
            } else {
                $rateData = Rate::where('calculator', 'commision-calculator')->first();
                if ($rateData && isset($rateData->settings['loan_rate'])) {
                    $annualReturn = (float) $rateData->settings['loan_rate'];
                    $rateSource = 'db_admin';
                } else {
                    return $this->error('Expected annual return not provided in request or DB (loan_rate missing).', [], 422);
                }
            }

            $periodsPerYearMap = [
                'monthly' => 12,
                'quarterly' => 4,
                'half-yearly' => 2,
                'yearly' => 1,
            ];

            if (!isset($periodsPerYearMap[$freq])) {
                return $this->error('Invalid withdrawal_frequency provided.', ['withdrawal_frequency' => ['Invalid value']], 422);
            }

            $periodsPerYear = $periodsPerYearMap[$freq];

            // total periods
            if ($termUnit === 'years') {
                $totalPeriods = (int) round($termValue * $periodsPerYear);
            } else {
                $totalPeriods = (int) round(($termValue / 12.0) * $periodsPerYear);
            }

            if ($totalPeriods <= 0) {
                return $this->error('Withdrawal term results in zero periods. Provide a positive term.', [], 400);
            }

            // effective periodic rate
            $r = $annualReturn / 100.0;
            if (!is_finite($r) || $periodsPerYear <= 0) {
                return $this->error('Invalid annual return or frequency.', [], 400);
            }

            $periodicRate = pow(1 + $r, 1 / $periodsPerYear) - 1;

            // simulate
            $balance = $principal;
            $periodicWithdrawal = $withdrawal;
            $totalWithdrawn = 0.0;
            $totalReturns = 0.0;
            $periodsElapsed = 0;
            $corpusExhausted = false;

            $yearWise = [];
            $currentYear = 1;
            $periodsInCurrentYear = 0;
            $yearWithdrawn = 0.0;
            $yearReturns = 0.0;
            $yearStartingBalance = $balance;

            for ($p = 1; $p <= $totalPeriods; $p++) {
                $interest = $balance * $periodicRate;
                $balance += $interest;
                $totalReturns += $interest;
                $yearReturns += $interest;

                $actualWithdrawal = min($periodicWithdrawal, $balance);
                $balance -= $actualWithdrawal;
                $totalWithdrawn += $actualWithdrawal;
                $yearWithdrawn += $actualWithdrawal;

                $periodsElapsed++;
                $periodsInCurrentYear++;

                if ($balance <= 0.0000001) {
                    $balance = 0.0;
                    $corpusExhausted = true;

                    if ($includeYearWise) {
                        $yearWise[] = [
                            'year' => $currentYear,
                            'starting_balance' => round($yearStartingBalance, 2),
                            'total_withdrawn' => round($yearWithdrawn, 2),
                            'total_returns' => round($yearReturns, 2),
                            'ending_balance' => round($balance, 2),
                        ];
                    }
                    break;
                }

                if ($periodsInCurrentYear >= $periodsPerYear) {
                    if ($adjType === 'percent') {
                        $periodicWithdrawal *= (1 + $annualAdj / 100.0);
                    } else {
                        $periodicWithdrawal = max(0, $periodicWithdrawal + $annualAdj);
                    }

                    if ($includeYearWise) {
                        $yearWise[] = [
                            'year' => $currentYear,
                            'starting_balance' => round($yearStartingBalance, 2),
                            'total_withdrawn' => round($yearWithdrawn, 2),
                            'total_returns' => round($yearReturns, 2),
                            'ending_balance' => round($balance, 2),
                        ];
                    }

                    $currentYear++;
                    $periodsInCurrentYear = 0;
                    $yearWithdrawn = 0.0;
                    $yearReturns = 0.0;
                    $yearStartingBalance = $balance;
                }
            }

            $data = [
                'final_balance' => round($balance, 2),
                'total_withdrawals' => round($totalWithdrawn, 2),
                'total_returns' => round($totalReturns, 2),
                'periods_elapsed' => $periodsElapsed,
                'periods_total' => $totalPeriods,
                'periods_per_year' => $periodsPerYear,
                'corpus_exhausted' => $corpusExhausted,
                'effective_periodic_rate_percent' => round($periodicRate * 100, 6),
                'rate_used' => [
                    'annual_return_percent' => $annualReturn,
                    'rate_source' => $rateSource,
                ],
                'year_wise' => $includeYearWise ? $yearWise : null,
            ];

            $msg = $corpusExhausted
                ? "Corpus exhausted after " . round($periodsElapsed / $periodsPerYear, 2) . " year(s)."
                : "Corpus lasted the full term of {$termValue} {$termUnit}.";

            return $this->success($msg, $data, 200);

        } catch (Throwable $e) {
            Log::error('SWP calculate exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);

            return $this->error('Unexpected error occurred during calculation.', [
                'exception_message' => $e->getMessage(),
                'exception_trace' => config('app.debug') ? substr($e->getTraceAsString(), 0, 1000) : 'hidden',
            ], 500);
        }
    }
}
