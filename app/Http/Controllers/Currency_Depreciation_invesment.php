<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Currency_Depreciation_invesment extends Controller
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
     * POST /api/currency-depreciation/calculate
     *
     * Body JSON:
     * {
     *   "initial_foreign_amount": 100000,
     *   "base_exchange_rate": 45.89,
     *   "future_exchange_rate": 600,
     *   "annual_investment_growth_rate_percent": 55,   // optional, default 0
     *   "time_horizon_years": 5
     * }
     *
     * Returns data fields:
     * - initial_local_value
     * - future_local_no_growth
     * - net_change_no_growth
     * - net_change_no_growth_percent
     * - future_foreign_with_growth
     * - future_local_with_growth
     * - net_change_with_growth
     * - net_change_with_growth_percent
     */
    public function calculate(Request $request): JsonResponse
    {
        $rules = [
            'initial_foreign_amount' => 'required|numeric|min:0',
            'base_exchange_rate' => 'required|numeric|min:0',
            'future_exchange_rate' => 'required|numeric|min:0',
            'annual_investment_growth_rate_percent' => 'nullable|numeric',
            'time_horizon_years' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        // Inputs
        $foreign = (float) $request->input('initial_foreign_amount');
        $baseRate = (float) $request->input('base_exchange_rate'); // local per unit now
        $futureRate = (float) $request->input('future_exchange_rate'); // local per unit future
        $growthPct = $request->has('annual_investment_growth_rate_percent')
            ? (float) $request->input('annual_investment_growth_rate_percent')
            : 0.0;
        $years = (float) $request->input('time_horizon_years');

        // Convert percents to decimals
        $growthRate = $growthPct / 100.0;

        // Calculations

        // 1) Initial local value
        $initialLocal = $foreign * $baseRate;

        // 2) Future value in local currency (no growth) -- simply exchange at future rate
        $futureLocalNoGrowth = $foreign * $futureRate;

        // 3) Net change (no growth)
        $netNoGrowth = $futureLocalNoGrowth - $initialLocal;
        $netNoGrowthPct = $initialLocal > 0 ? ($netNoGrowth / $initialLocal) * 100.0 : null;

        // 4) With investment growth: future value in foreign currency after compounding
        // FV_foreign = foreign * (1 + growthRate)^years
        $futureForeignWithGrowth = $foreign * pow(1 + $growthRate, $years);

        // 5) Convert that future foreign to local at future exchange rate
        $futureLocalWithGrowth = $futureForeignWithGrowth * $futureRate;

        // 6) Net change (with growth)
        $netWithGrowth = $futureLocalWithGrowth - $initialLocal;
        $netWithGrowthPct = $initialLocal > 0 ? ($netWithGrowth / $initialLocal) * 100.0 : null;

        // Round values for currency display
        $initialLocalR = round($initialLocal, 2);
        $futureLocalNoGrowthR = round($futureLocalNoGrowth, 2);
        $netNoGrowthR = round($netNoGrowth, 2);
        $netNoGrowthPctR = is_null($netNoGrowthPct) ? null : round($netNoGrowthPct, 2);

        $futureForeignWithGrowthR = round($futureForeignWithGrowth, 2);
        $futureLocalWithGrowthR = round($futureLocalWithGrowth, 2);
        $netWithGrowthR = round($netWithGrowth, 2);
        $netWithGrowthPctR = is_null($netWithGrowthPct) ? null : round($netWithGrowthPct, 2);

        $data = [
            'initial_local_value' => $initialLocalR,
            'future_local_no_growth' => $futureLocalNoGrowthR,
            'net_change_no_growth' => $netNoGrowthR,
            'net_change_no_growth_percent' => $netNoGrowthPctR,

            'future_foreign_with_growth' => $futureForeignWithGrowthR,
            'future_local_with_growth' => $futureLocalWithGrowthR,
            'net_change_with_growth' => $netWithGrowthR,
            'net_change_with_growth_percent' => $netWithGrowthPctR,
        ];

        return $this->success('Currency depreciation calculation completed.', $data, 200);
    }
}
