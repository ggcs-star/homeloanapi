<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Illuminate\Support\Facades\Log;

class Future_value_of_aniteam_inflation extends Controller
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

    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'current_cost' => 'required|numeric|min:0',
            'annual_inflation_rate' => 'required|numeric',
            'years' => 'required|numeric|min:0',
            'term_unit' => 'required|string|in:years,months',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        try {
            $current = (float) $request->input('current_cost');
            $annualInflation = (float) $request->input('annual_inflation_rate');
            $termValue = (float) $request->input('years');
            $termUnit = $request->input('term_unit');

            $r = $annualInflation / 100.0;

            if ($termUnit === 'years') {
                $n = $termValue;
                $future = $current * pow(1 + $r, $n);
            } else {
                $months = (int) round($termValue);
                $monthlyRate = $r / 12.0;
                $future = $current * pow(1 + $monthlyRate, $months);
            }

            $futureRounded = round($future, 2);

            $data = [
                'future_cost' => $futureRounded
            ];

            return $this->success('Future cost calculated successfully.', $data, 200);

        } catch (Throwable $e) {
            Log::error('Inflation futureCost exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);

            $errPayload = [
                'exception_message' => $e->getMessage(),
                'exception_trace' => config('app.debug') ? substr($e->getTraceAsString(), 0, 1000) : 'hidden',
            ];

            return $this->error('Unexpected error occurred while calculating future cost.', $errPayload, 500);
        }
    }
}
