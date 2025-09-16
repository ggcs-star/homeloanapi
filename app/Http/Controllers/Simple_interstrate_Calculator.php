<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

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
     *   "annual_interest_rate": 8,    // optional (user input or DB से आएगा)
     *   "term": 5,
     *   "term_unit": "years"          // "years" or "months"
     * }
     */
    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'principal' => 'required|numeric|min:0',
            'annual_interest_rate' => 'sometimes|numeric|min:0', // ✅ optional now
            'term' => 'required|numeric|min:0',
            'term_unit' => 'required|string|in:years,months',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // Inputs
        $P = (float) $request->input('principal');
        $term = (float) $request->input('term');
        $termUnit = $request->input('term_unit');

        // ✅ Interest Rate: User → DB → Error
        if ($request->filled('annual_interest_rate')) {
            $annualRatePct = (float) $request->input('annual_interest_rate');
            $rateSource = 'user_input';
        } else {
            $rateData = Rate::where('calculator', 'commision-calculator')->first();
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $annualRatePct = (float) $rateData->settings['loan_rate'];
                $rateSource = 'db_admin';
            } else {
                return $this->error('Annual interest rate not provided in request or DB.', [], 422);
            }
        }

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
            'final_amount'   => $finalAmount,
            'rate_used_percent' => $annualRatePct,
            'rate_source'    => $rateSource,
        ];

        return $this->success('Simple interest calculated successfully.', $data, 200);
    }
}
