<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Relocation_opportunity_calculator extends Controller
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

    /**
     * POST /api/relocation/calculate
     *
     * Full robust calculation. Accepts expected return either as percent (15) or decimal (0.15).
     */
   public function calculate(Request $request): JsonResponse
{
    $rules = [
        'current_annual_salary' => 'required|numeric|min:0',
        'proposed_new_salary' => 'required|numeric|min:0',
        'current_tax_rate_percent' => 'required|numeric|min:0|max:100',
        'new_tax_rate_percent' => 'required|numeric|min:0|max:100',
        'current_col_index' => 'required|numeric|min:0.000001',
        'new_col_index' => 'required|numeric|min:0.000001',
        'moving_expenses' => 'required|numeric|min:0',
        'temporary_housing' => 'required|numeric|min:0',
        'other_relocation_costs' => 'required|numeric|min:0',
        'time_horizon_years' => 'required|numeric|min:0',
        'expected_annual_investment_return_percent' => 'nullable|numeric',
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
        return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
    }

    // Inputs
    $currentSalary = (float) $request->input('current_annual_salary');
    $proposedSalary = (float) $request->input('proposed_new_salary');
    $currentTaxPct = (float) $request->input('current_tax_rate_percent');
    $newTaxPct = (float) $request->input('new_tax_rate_percent');
    $currentCol = (float) $request->input('current_col_index');
    $newCol = (float) $request->input('new_col_index');
    $moving = (float) $request->input('moving_expenses');
    $tempHousing = (float) $request->input('temporary_housing');
    $otherCosts = (float) $request->input('other_relocation_costs');
    $years = (float) $request->input('time_horizon_years');
    $expectedReturnInputRaw = $request->has('expected_annual_investment_return_percent')
        ? $request->input('expected_annual_investment_return_percent')
        : 0.0;

    $expectedReturnInput = is_numeric($expectedReturnInputRaw) ? (float) $expectedReturnInputRaw : 0.0;

    // Step 1: current net salary after tax
    $currentNetSalary = $currentSalary * (1.0 - ($currentTaxPct / 100.0));

    // Step 2: CoL adjust proposed salary into current-loc equivalent then apply new tax
    $colAdjustedGross = ($newCol != 0.0) ? ($proposedSalary * ($currentCol / $newCol)) : 0.0;
    $adjustedNewSalary = $colAdjustedGross * (1.0 - ($newTaxPct / 100.0));

    // Step 3: annual difference and totals
    $annualDifference = $adjustedNewSalary - $currentNetSalary;
    $totalIncomeDifference = $annualDifference * $years;

    // Step 4: relocation one-time costs
    $totalRelocationExpenses = $moving + $tempHousing + $otherCosts;

    // Step 5: net benefit (gain/loss) = totalIncomeDifference - relocation costs
    $netBenefit = $totalIncomeDifference - $totalRelocationExpenses;

    // Step 6: interpret expected return robustly (percent or decimal)
    if (abs($expectedReturnInput) > 1.0) {
        $r = $expectedReturnInput / 100.0;
    } else {
        $r = $expectedReturnInput;
    }

    // --- Step 7: FUTURE VALUE matching screenshot:
    // The screenshot calculates the FV of investing the ANNUAL salary DIFFERENCE each year
    // (ordinary annuity, end-of-year contributions):
    // FV = annualDifference * ( (1 + r)^years - 1 ) / r
    // If r == 0 => FV = annualDifference * years
    if ($years <= 0.0 || abs($annualDifference) < 1e-12) {
        $futureValueIfInvested = 0.0;
    } else {
        if ($r == 0.0) {
            $futureValueIfInvested = $annualDifference * $years;
        } else {
            // guard against invalid r values (r <= -1 makes pow invalid)
            if (1.0 + $r <= 0.0) {
                $futureValueIfInvested = null;
            } else {
                $factor = pow(1.0 + $r, $years) - 1.0;
                $futureValueIfInvested = $annualDifference * ($factor / $r);
            }
        }
    }

    $recommendation = ($netBenefit > 0.0)
        ? 'Relocation is financially beneficial.'
        : (($netBenefit < 0.0) ? 'Relocation results in a net loss.' : 'Relocation breaks even.');

    $data = [
        'current_net_salary' => round($currentNetSalary, 2),
        'adjusted_new_salary_col_adjusted' => round($adjustedNewSalary, 2),
        'annual_salary_difference' => round($annualDifference, 2),
        'total_income_difference' => round($totalIncomeDifference, 2),
        'total_relocation_expenses' => round($totalRelocationExpenses, 2),
        'net_benefit' => round($netBenefit, 2),
        'future_value_if_invested' => is_null($futureValueIfInvested) ? null : round($futureValueIfInvested, 3),
        'recommendation' => $recommendation,
    ];

    return $this->success('Relocation calculation completed successfully.', $data, 200);
}

}
