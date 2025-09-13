<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Simple_interstrate_Calculator extends Controller
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
     * POST /api/simple-interest/calculate
     * Body JSON:
     * {
     *   "principal": 10000,
     *   "annual_interest_rate": 8,    // percent
     *   "term": 5,
     *   "term_unit": "years"          // "years" or "months"
     * }
     *
     * Returns:
     * {
     *   status, message, error, data: { total_interest, final_amount }
     * }
     */
    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'principal' => 'required|numeric|min:0',
            'annual_interest_rate' => 'required|numeric',
            'term' => 'required|numeric|min:0',
            'term_unit' => 'required|string|in:years,months',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // Inputs
        $P = (float) $request->input('principal');
        $annualRatePct = (float) $request->input('annual_interest_rate');
        $term = (float) $request->input('term');
        $termUnit = $request->input('term_unit');

        // Convert annual rate percent to decimal
        $r = $annualRatePct / 100.0;

        // Convert term to years (simple interest uses years)
        $years = $termUnit === 'years' ? $term : ($term / 12.0);

        // Simple interest = P * r * t
        $totalInterest = $P * $r * $years;

        // Final amount = principal + interest
        $finalAmount = $P + $totalInterest;

        // Round to 2 decimals (currency)
        $totalInterest = round($totalInterest, 2);
        $finalAmount = round($finalAmount, 2);

        $data = [
            'total_interest' => $totalInterest,
            'final_amount' => $finalAmount,
        ];

        return $this->success('Simple interest calculated successfully.', $data, 200);
    }

}
