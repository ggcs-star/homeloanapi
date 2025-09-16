<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rate;

class DebtCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $debtsInput = $request->input('debts', []);
        $extraPayment = (float) $request->input('extra_payment', 0);

        if (empty($debtsInput)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debts are required',
                'error' => 'Debts are required',
                'data' => null
            ], 400);
        }

        // ✅ Fetch admin loan_rate from DB once
        $rateData = Rate::where('calculator', 'commision-calculator')->first();
        $adminLoanRate = null;
        if ($rateData && isset($rateData->settings['loan_rate'])) {
            $adminLoanRate = (float) $rateData->settings['loan_rate'];
        } elseif ($rateData && isset($rateData->settings['interest_rate'])) {
            $adminLoanRate = (float) $rateData->settings['interest_rate'];
        } else {
            $adminLoanRate = 10; // fallback
        }

        $results = [];

        foreach (['avalanche', 'snowball'] as $method) {
            // Copy debts + fill rates from user or DB
            $debts = array_map(function ($d) use ($adminLoanRate) {
                if (isset($d['rate']) && $d['rate'] !== null && $d['rate'] !== '') {
                    $rate = (float) $d['rate'];
                    $rateSource = 'user';
                } else {
                    $rate = $adminLoanRate;
                    $rateSource = 'admin';
                }

                return [
                    'name' => $d['name'],
                    'balance' => round($d['balance'], 2),
                    'rate' => round($rate, 2),
                    'rate_source' => $rateSource,
                    'min_payment' => round($d['min_payment'], 2),
                    'total_interest' => 0,
                    'total_paid' => 0
                ];
            }, $debtsInput);

            $months = 0;
            $maxMonths = 600;

            while (array_filter($debts, fn($d) => $d['balance'] > 0) && $months < $maxMonths) {
                $months++;
                $paymentLeft = $extraPayment;

                // Sort debts
                if ($method === 'avalanche') {
                    usort($debts, fn($a, $b) => $b['rate'] <=> $a['rate']);
                } else {
                    usort($debts, fn($a, $b) => $a['balance'] <=> $b['balance']);
                }

                foreach ($debts as &$debt) {
                    if ($debt['balance'] <= 0) continue;

                    $balanceCents = round($debt['balance'] * 100);
                    $minPaymentCents = round($debt['min_payment'] * 100);
                    $paymentLeftCents = round($paymentLeft * 100);

                    $monthlyRate = $debt['rate'] / 12 / 100;
                    $interestCents = round($balanceCents * $monthlyRate);

                    $paymentCents = $minPaymentCents;

                    // Apply extra payment
                    if ($paymentLeftCents > 0) {
                        $paymentCents += $paymentLeftCents;
                        $paymentLeftCents = 0;
                    }

                    // Prevent overpayment
                    if ($paymentCents > $balanceCents + $interestCents) {
                        $paymentLeftCents = $paymentCents - ($balanceCents + $interestCents);
                        $paymentCents = $balanceCents + $interestCents;
                    }

                    // Update debt
                    $balanceCents = $balanceCents + $interestCents - $paymentCents;
                    $debt['balance'] = round($balanceCents / 100, 2);
                    $debt['total_interest'] += round($interestCents / 100, 2);
                    $debt['total_paid'] += round($paymentCents / 100, 2);
                }

                $extraPayment = $paymentLeftCents / 100; // carry over
            }

            $totalPaid = round(array_sum(array_column($debts, 'total_paid')), 2);
            $totalInterest = round(array_sum(array_column($debts, 'total_interest')), 2);

            $results[$method] = [
                'total_months' => $months,
                'total_interest_paid' => $totalInterest,
                'total_amount_paid' => $totalPaid,
                'debts' => $debts
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Debt calculation completed successfully',
            'error' => null,
            'data' => $results
        ]);
    }
}
