<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TwoCarTcoController extends Controller
{
    public function compare(Request $request)
    {
        $rules = [
            // Car A
            'car_a.purchase_price' => 'required|numeric|min:0',
            'car_a.down_payment' => 'required|numeric|min:0',
            'car_a.interest_rate_percent' => 'required|numeric|min:0',
            'car_a.loan_tenure_years' => 'required|numeric|min:0',
            'car_a.fuel_efficiency_km_per_l' => 'required|numeric|min:0',
            'car_a.annual_distance_km' => 'required|numeric|min:0',
            'car_a.fuel_price_per_l' => 'required|numeric|min:0',
            'car_a.annual_maintenance' => 'required|numeric|min:0',
            'car_a.annual_insurance' => 'required|numeric|min:0',
            'car_a.resale_value_after_ownership' => 'required|numeric|min:0',

            // Car B (same fields)
            'car_b.purchase_price' => 'required|numeric|min:0',
            'car_b.down_payment' => 'required|numeric|min:0',
            'car_b.interest_rate_percent' => 'required|numeric|min:0',
            'car_b.loan_tenure_years' => 'required|numeric|min:0',
            'car_b.fuel_efficiency_km_per_l' => 'required|numeric|min:0',
            'car_b.annual_distance_km' => 'required|numeric|min:0',
            'car_b.fuel_price_per_l' => 'required|numeric|min:0',
            'car_b.annual_maintenance' => 'required|numeric|min:0',
            'car_b.annual_insurance' => 'required|numeric|min:0',
            'car_b.resale_value_after_ownership' => 'required|numeric|min:0',

            // ownership duration
            'ownership_years' => 'required|integer|min:1',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $request->all();
        $years = intval($payload['ownership_years']);

        $carA = $this->computeTcoWithLoan($payload['car_a'], $years);
        $carB = $this->computeTcoWithLoan($payload['car_b'], $years);

        $recommendation = ($carA['total_tco'] < $carB['total_tco']) ? 'Car A' : 'Car B';

        // Return in the exact TCO Summary format (like your screenshot)
        return response()->json([
            'ownership_duration_years' => $years,
            'tco_summary' => [
                'car_a' => [
                    'financing_and_interest' => round($carA['loan_principal'], 0), // shown in table cell
                    'fuel' => round($carA['fuel'], 0),
                    'maintenance' => round($carA['maintenance'], 0),
                    'insurance' => round($carA['insurance'], 0),
                    'depreciation' => round($carA['depreciation'], 0),
                    'total_tco' => round($carA['total_tco'], 0) // includes total_loan_repayment + others
                ],
                'car_b' => [
                    'financing_and_interest' => round($carB['loan_principal'], 0),
                    'fuel' => round($carB['fuel'], 0),
                    'maintenance' => round($carB['maintenance'], 0),
                    'insurance' => round($carB['insurance'], 0),
                    'depreciation' => round($carB['depreciation'], 0),
                    'total_tco' => round($carB['total_tco'], 0)
                ],
                'recommendation' => $recommendation
            ]
        ]);
    }

    /**
     * Compute TCO and loan totals. Returns array with:
     * loan_principal, total_loan_repayment, total_interest, fuel, maintenance, insurance, depreciation, total_tco
     */
    private function computeTcoWithLoan(array $d, int $years): array
    {
        $purchasePrice = floatval($d['purchase_price']);
        $downPayment   = floatval($d['down_payment']);
        $interestPercent = floatval($d['interest_rate_percent']);
        $loanTenureYears = floatval($d['loan_tenure_years']);
        $fuelEff       = floatval($d['fuel_efficiency_km_per_l']);
        $annualKm      = floatval($d['annual_distance_km']);
        $fuelPrice     = floatval($d['fuel_price_per_l']);
        $annualMaint   = floatval($d['annual_maintenance']);
        $annualIns     = floatval($d['annual_insurance']);
        $resale        = floatval($d['resale_value_after_ownership']);

        // Loan principal
        $loan_principal = max(0.0, $purchasePrice - $downPayment);

        // EMI & total loan repayment (principal + interest)
        $loan_months = max(0, intval(round($loanTenureYears * 12)));
        $monthlyRate = ($interestPercent / 100.0) / 12.0;
        $emi = 0.0;
        $total_loan_repayment = 0.0;
        $total_interest = 0.0;
        if ($loan_months > 0 && $loan_principal > 0) {
            if ($monthlyRate == 0.0) {
                $emi = $loan_principal / $loan_months;
            } else {
                $emi = ($loan_principal * $monthlyRate * pow(1 + $monthlyRate, $loan_months))
                       / (pow(1 + $monthlyRate, $loan_months) - 1);
            }
            $total_loan_repayment = $emi * $loan_months;
            $total_interest = max(0.0, $total_loan_repayment - $loan_principal);
        }

        // Fuel cost over ownership period
        $fuel_per_year = ($fuelEff > 0) ? ($annualKm / $fuelEff) : 0.0;
        $fuel = $fuel_per_year * $fuelPrice * $years;

        // Maintenance & insurance totals
        $maintenance = $annualMaint * $years;
        $insurance = $annualIns * $years;

        // Depreciation (purchase - resale)
        $depreciation = $purchasePrice - $resale;

        // IMPORTANT: Total TCO = TOTAL LOAN REPAYMENT (principal+interest) + fuel + maintenance + insurance + depreciation
        // This matches the screenshot numbers (even though financing cell shows principal only).
        $total_tco = $total_loan_repayment + $fuel + $maintenance + $insurance + $depreciation;

        return [
            'loan_principal' => round($loan_principal, 2),
            'emi' => round($emi, 2),
            'total_loan_repayment' => round($total_loan_repayment, 2),
            'total_interest' => round($total_interest, 2),
            'fuel' => round($fuel, 2),
            'maintenance' => round($maintenance, 2),
            'insurance' => round($insurance, 2),
            'depreciation' => round($depreciation, 2),
            'total_tco' => round($total_tco, 2)
        ];
    }
}
