<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

class SipCalculatorController extends Controller
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
        $rules = [
            'lumpsum'   => 'nullable|numeric|min:0',
            'deposit'   => 'required|numeric|min:1',
            'frequency' => 'required|string|in:weekly,monthly,quarterly,half-yearly,yearly',
            'annual_rate' => 'sometimes|numeric|min:0', // ✅ अब required नहीं
            'years'     => 'required|numeric|min:1',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        $lumpsum = (float) $request->input('lumpsum', 0);
        $P       = (float) $request->input('deposit');
        $t       = (int) $request->input('years');

        // ✅ Annual Rate (User → DB → Error)
        if ($request->filled('annual_rate')) {
            $annualRate = (float) $request->input('annual_rate');
            $rateSource = 'user_input';
        } else {
            $rateData = Rate::where('calculator', 'commision-calculator')->first();
            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $annualRate = (float) $rateData->settings['loan_rate'];
                $rateSource = 'db_admin';
            } else {
                return $this->error('Annual rate not provided in request or DB (loan_rate missing).', [], 422);
            }
        }

        $r = $annualRate / 100;

        // Frequency mapping
        $frequencyMap = [
            'weekly'       => 52,
            'monthly'      => 12,
            'quarterly'    => 4,
            'half-yearly'  => 2,
            'yearly'       => 1,
        ];

        $n = $frequencyMap[strtolower($request->input('frequency'))];

        // Future value of SIP
        $fv_sip = $P * ((pow(1 + $r/$n, $n*$t) - 1) / ($r/$n)) * (1 + $r/$n);

        // Future value of Lumpsum
        $fv_lumpsum = $lumpsum * pow(1 + $r/$n, $n*$t);

        $future_value = $fv_sip + $fv_lumpsum;

        $data = [
            'initial_lumpsum'       => $lumpsum,
            'regular_deposit'       => $P,
            'deposit_frequency'     => $request->input('frequency'),
            'annual_rate_percent'   => $annualRate,
            'rate_source'           => $rateSource,
            'years'                 => $t,
            'invested_amount'       => ($P * $n * $t) + $lumpsum,
            'maturity_value'        => round($future_value, 2),
            'total_interest_earned' => round($future_value - (($P * $n * $t) + $lumpsum), 2),
        ];

        return $this->success('SIP calculation completed successfully.', $data, 200);
    }
}
