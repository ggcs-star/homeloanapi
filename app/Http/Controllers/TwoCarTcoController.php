<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate; // ✅ DB से fetch करने के लिए

class TwoCarTcoController extends Controller
{
    public function compare(Request $request)
    {
        $rules = [
            // Car A
            'car_a.purchase_price' => 'required|numeric|min:0',
            'car_a.down_payment' => 'required|numeric|min:0',
            'car_a.interest_rate_percent' => 'nullable|numeric|min:0', // ✅ अब optional
            'car_a.loan_tenure_years' => 'required|numeric|min:0',
            'car_a.fuel_efficiency_km_per_l' => 'required|numeric|min:0',
            'car_a.annual_distance_km' => 'required|numeric|min:0',
            'car_a.fuel_price_per_l' => 'required|numeric|min:0',
            'car_a.annual_maintenance' => 'required|numeric|min:0',
            'car_a.annual_insurance' => 'required|numeric|min:0',
            'car_a.resale_value_after_ownership' => 'required|numeric|min:0',

            // Car B
            'car_b.purchase_price' => 'required|numeric|min:0',
            'car_b.down_payment' => 'required|numeric|min:0',
            'car_b.interest_rate_percent' => 'nullable|numeric|min:0', // ✅ अब optional
            'car_b.loan_tenure_years' => 'required|numeric|min:0',
            'car_b.fuel_efficiency_km_per_l' => 'required|numeric|min:0',
            'car_b.annual_distance_km' => 'required|numeric|min:0',
            'car_b.fuel_price_per_l' => 'required|numeric|min:0',
            'car_b.annual_maintenance' => 'required|numeric|min:0',
            'car_b.annual_insurance' => 'required|numeric|min:0',
            'car_b.resale_value_after_ownership' => 'required|numeric|min:0',

            'ownership_years' => 'required|integer|min:1',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $request->all();
        $years = intval($payload['ownership_years']);

        // ✅ DB से loan_rate fetch करो
        $rateData = Rate::where('calculator', 'commision-calculator')->first();
        $adminLoanRate = $rateData->settings['loan_rate'] ?? null;

        $carA = $this->computeTcoWithLoan($payload['car_a'], $years, $adminLoanRate);
        $carB = $this->computeTcoWithLoan($payload['car_b'], $years, $adminLoanRate);

        $recommendation = ($carA['total_tco'] < $carB['total_tco']) ? 'Car A' : 'Car B';

        return response()->json([
            'ownership_duration_years' => $years,
            'tco_summary' => [
                'car_a' => [
                    'financing_and_interest' => round($carA['loan_principal'], 0),
                    'fuel' => round($carA['fuel'], 0),
                    'maintenance' => round($carA['maintenance'], 0),
                    'insurance' => round($carA['insurance'], 0),
                    'depreciation' => round($carA['depreciation'], 0),
                    'total_tco' => round($carA['total_tco'], 0),
                    'loan_rate_used' => $carA['loan_rate_used'],
                    'loan_rate_source' => $carA['loan_rate_source'],
                ],
                'car_b' => [
                    'financing_and_interest' => round($carB['loan_principal'], 0),
                    'fuel' => round($carB['fuel'], 0),
                    'maintenance' => round($carB['maintenance'], 0),
                    'insurance' => round($carB['insurance'], 0),
                    'depreciation' => round($carB['depreciation'], 0),
                    'total_tco' => round($carB['total_tco'], 0),
                    'loan_rate_used' => $carB['loan_rate_used'],
                    'loan_rate_source' => $carB['loan_rate_source'],
                ],
                'recommendation' => $recommendation
            ]
        ]);
    }

    private function computeTcoWithLoan(array $d, int $years, ?float $adminLoanRate): array
    {
        $purchasePrice = floatval($d['purchase_price']);
        $downPayment   = floatval($d['down_payment']);

        // ✅ Loan rate: User → DB → Error
        if (!empty($d['interest_rate_percent'])) {
            $interestPercent = floatval($d['interest_rate_percent']);
            $loanRateSource = 'user_input';
        } elseif ($adminLoanRate !== null) {
            $interestPercent = (float) $adminLoanRate;
            $loanRateSource = 'db_admin';
        } else {
            throw new \Exception('Loan interest rate not provided in request or DB.');
        }

        $loanTenureYears = floatval($d['loan_tenure_years']);
        $fuelEff       = floatval($d['fuel_efficiency_km_per_l']);
        $annualKm      = floatval($d['annual_distance_km']);
        $fuelPrice     = floatval($d['fuel_price_per_l']);
        $annualMaint   = floatval($d['annual_maintenance']);
        $annualIns     = floatval($d['annual_insurance']);
        $resale        = floatval($d['resale_value_after_ownership']);

        $loan_principal = max(0.0, $purchasePrice - $downPayment);

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

        $fuel_per_year = ($fuelEff > 0) ? ($annualKm / $fuelEff) : 0.0;
        $fuel = $fuel_per_year * $fuelPrice * $years;

        $maintenance = $annualMaint * $years;
        $insurance = $annualIns * $years;

        $depreciation = $purchasePrice - $resale;

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
            'total_tco' => round($total_tco, 2),
            'loan_rate_used' => $interestPercent,
            'loan_rate_source' => $loanRateSource,
        ];
    }
}
