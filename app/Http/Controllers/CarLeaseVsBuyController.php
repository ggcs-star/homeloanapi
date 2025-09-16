<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Rate;

class CarLeaseVsBuyController extends Controller
{
    public function compare(Request $request)
    {
        $rules = [
            'car_price' => 'required|numeric|min:0',
            'analysis_period_years' => 'required|numeric|min:1',
            'opportunity_cost_percent' => 'required|numeric|min:0',

            'lease.deposit' => 'required|numeric|min:0',
            'lease.monthly_payment' => 'required|numeric|min:0',
            'lease.annual_maintenance' => 'nullable|numeric|min:0',

            'buy.down_payment' => 'required|numeric|min:0',
            'buy.loan_interest_rate_percent' => 'nullable|numeric|min:0', // optional
            'buy.loan_tenure_years' => 'required|numeric|min:0',
            'buy.annual_maintenance' => 'nullable|numeric|min:0',
            'buy.annual_depreciation_percent' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        $carPrice = floatval($data['car_price']);
        $years = intval($data['analysis_period_years']);
        $analysisMonths = $years * 12;

        // ✅ Loan Rate (user → admin → fallback 10)
        if (!empty($data['buy']['loan_interest_rate_percent'])) {
            $buyInterestPercent = floatval($data['buy']['loan_interest_rate_percent']);
            $loanRateSource = 'user';
        } else {
            $calculatorName = class_basename(__CLASS__); // "CarLeaseVsBuyController"
            $rateData = Rate::where('calculator',  'commision-calculator')->first();

            if ($rateData && isset($rateData->settings['loan_rate'])) {
                $buyInterestPercent = (float) $rateData->settings['loan_rate'];
            } elseif ($rateData && isset($rateData->settings['interest_rate'])) {
                $buyInterestPercent = (float) $rateData->settings['interest_rate'];
            } else {
                $buyInterestPercent = 10; // fallback
            }

            $loanRateSource = 'admin';
        }

        // ✅ Opportunity cost हमेशा user से
        $opportunityRatePercent = floatval($data['opportunity_cost_percent']);
        $opportunityRate = $opportunityRatePercent / 100.0;

        // --- Leasing ---
        $leaseDeposit = floatval($data['lease']['deposit']);
        $leaseMonthly = floatval($data['lease']['monthly_payment']);
        $leaseAnnualMaintenance = floatval($data['lease']['annual_maintenance'] ?? 0.0);

        $totalLeasePayments = $leaseDeposit + ($leaseMonthly * $analysisMonths);
        $leaseMaintenanceTotal = $leaseAnnualMaintenance * $years;

        $buyDown = floatval($data['buy']['down_payment']);
        $opportunityBenefit = ($buyDown - $leaseDeposit) * (pow(1 + $opportunityRate, $years) - 1);

        $netLeasingCost = $totalLeasePayments + $leaseMaintenanceTotal - $opportunityBenefit;

        // --- Buying ---
        $buyTenureYears = floatval($data['buy']['loan_tenure_years']);
        $buyAnnualMaintenance = floatval($data['buy']['annual_maintenance'] ?? 0.0);
        $buyDepPercent = floatval($data['buy']['annual_depreciation_percent']) / 100.0;

        $loanPrincipal = max(0.0, $carPrice - $buyDown);
        $emi = 0.0;
        $totalLoanRepayment = 0.0;
        $loanMonths = intval(round($buyTenureYears * 12));

        if ($loanMonths > 0 && $loanPrincipal > 0) {
            $monthlyRate = ($buyInterestPercent / 100.0) / 12.0;
            if ($monthlyRate == 0.0) {
                $emi = $loanPrincipal / $loanMonths;
            } else {
                $emi = ($loanPrincipal * $monthlyRate * pow(1 + $monthlyRate, $loanMonths)) / (pow(1 + $monthlyRate, $loanMonths) - 1);
            }
            $totalLoanRepayment = $emi * $loanMonths;
        }

        $buyMaintenanceTotal = $buyAnnualMaintenance * $years;
        $estimatedResaleValue = $carPrice * pow(1 - $buyDepPercent, $years);
        $netBuyingCost = $buyDown + $totalLoanRepayment + $buyMaintenanceTotal - $estimatedResaleValue;

        // --- Response ---
        $result = [
            'inputs' => $data,
            'summary_comparison' => [
                'leasing_net' => round($netLeasingCost, 2),
                'buying_net'  => round($netBuyingCost, 2),
                'recommendation' => ($netBuyingCost < $netLeasingCost) ? 'Buy' : 'Lease',
            ],
            'detailed_breakdown' => [
                'leasing' => [
                    'total_lease_payments' => round($totalLeasePayments, 2),
                    'lease_maintenance_total' => round($leaseMaintenanceTotal, 2),
                    'opportunity_cost_benefit' => round($opportunityBenefit, 2),
                    'net_leasing_cost' => round($netLeasingCost, 2),
                ],
                'buying' => [
                    'down_payment' => round($buyDown, 2),
                    'total_loan_repayment' => round($totalLoanRepayment, 2),
                    'buy_maintenance_total' => round($buyMaintenanceTotal, 2),
                    'estimated_resale_value' => round($estimatedResaleValue, 2),
                    'net_buying_cost' => round($netBuyingCost, 2),
                    'monthly_emi' => round($emi, 2),
                ],
            ],
            'rates_used' => [
                'loan_interest_rate_percent' => $buyInterestPercent,
                'loan_rate_source' => $loanRateSource
            ]
        ];

        return response()->json($result);
    }
}
