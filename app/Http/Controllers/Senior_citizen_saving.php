<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Senior_citizen_saving extends Controller
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

    protected function error(string $message, array $errorPayload = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'error'   => $errorPayload,
            'data'    => null,
        ], $code);
    }

    /**
     * POST /api/scss/calculate
     * Body JSON:
     * {
     *   "deposit_amount": 100000,
     *   "annual_interest_rate": 5
     * }
     *
     * Always uses 5 years (20 quarters).
     */
    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'deposit_amount' => 'required|numeric|min:0|max:3000000',
            'annual_interest_rate' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        $P = (float) $request->input('deposit_amount');
        $annualRatePct = (float) $request->input('annual_interest_rate');

        // Fixed 5 years = 20 quarters
        $years = 5;
        $nQuarters = $years * 4;

        // Convert to decimal
        $r = $annualRatePct / 100.0;

        // Quarterly interest = P * (r/4)
        $quarterlyInterest = $P * ($r / 4);

        // Total interest over 5 years
        $totalInterest = $quarterlyInterest * $nQuarters;

        // Final balance (principal stays same, interest paid separately)
        $finalBalance = $P;

        $data = [
            'quarterly_interest' => round($quarterlyInterest, 2),
            'total_interest' => round($totalInterest, 2),
            'final_balance' => round($finalBalance, 2),
        ];

        return $this->success('SCSS calculation completed successfully.', $data, 200);
    }
}
