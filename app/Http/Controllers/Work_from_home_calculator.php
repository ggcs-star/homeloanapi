<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class Work_from_home_calculator extends Controller
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
            'wfh_days_per_week' => 'required|numeric|min:0|max:7',
            'working_hours_per_day' => 'required|numeric|min:0|max:24',
            'pc_power_watts' => 'required|numeric|min:0',
            'ac_power_watts' => 'sometimes|numeric|min:0',
            'electricity_rate' => 'required|numeric|min:0',
            'monthly_internet_bill' => 'required|numeric|min:0',
            'percent_internet_for_work' => 'required|numeric|min:0|max:100',
            'equipment_cost' => 'required|numeric|min:0',
            'equipment_lifespan_years' => 'required|numeric|min:0.5|max:50',
            'percent_equipment_for_work' => 'required|numeric|min:0|max:100',
            'monthly_rent_or_emi' => 'required|numeric|min:0',
            'percent_space_for_work' => 'required|numeric|min:0|max:100',
            'monthly_stationery_cost' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        try {
            // inputs
            $workingHoursPerDay = (float)$request->input('working_hours_per_day');
            $pcWatts = (float)$request->input('pc_power_watts');
            $acWatts = (float)$request->input('ac_power_watts', 0.0);
            $electricityRate = (float)$request->input('electricity_rate');

            $monthlyInternet = (float)$request->input('monthly_internet_bill');
            $pctInternetWork = (float)$request->input('percent_internet_for_work');

            $equipmentCost = (float)$request->input('equipment_cost');
            $equipmentLifeYears = (float)$request->input('equipment_lifespan_years');
            $pctEquipmentWork = (float)$request->input('percent_equipment_for_work');

            $monthlyRentOrEmi = (float)$request->input('monthly_rent_or_emi');
            $pctSpaceWork = (float)$request->input('percent_space_for_work');

            $monthlyStationery = (float)$request->input('monthly_stationery_cost');

            // ⚡ Electricity calculation (20 working days/month)
            $workingDaysPerMonth = 20;
            $monthlyWorkHours = $workingDaysPerMonth * $workingHoursPerDay;
            $totalWatts = $pcWatts + $acWatts;

            $monthlyKwh = ($totalWatts * $monthlyWorkHours) / 1000.0;
            $monthlyElectricityCost = $monthlyKwh * $electricityRate;
            $annualElectricityCost = $monthlyElectricityCost * 12.0;

            // 🌐 Internet (work portion)
            $monthlyInternetForWork = $monthlyInternet * ($pctInternetWork / 100.0);
            $annualInternetForWork = $monthlyInternetForWork * 12.0;

            // 💻 Equipment depreciation (work portion)
            $monthlyEquipmentDep = 0.0;
            if ($equipmentLifeYears > 0) {
                $monthlyEquipmentDep = ($equipmentCost * ($pctEquipmentWork / 100.0)) / ($equipmentLifeYears * 12.0);
            }
            $annualEquipmentDep = $monthlyEquipmentDep * 12.0;

            // 🏠 Home office space (work portion)
            $monthlyHomeOffice = $monthlyRentOrEmi * ($pctSpaceWork / 100.0);
            $annualHomeOffice = $monthlyHomeOffice * 12.0;

            // ✏️ Additional expenses
            $monthlyAdditional = $monthlyStationery;
            $annualAdditional = $monthlyAdditional * 12.0;

            // 📊 Totals
            $totalMonthly = $monthlyElectricityCost + $monthlyInternetForWork + $monthlyEquipmentDep + $monthlyHomeOffice + $monthlyAdditional;
            $totalAnnual = $annualElectricityCost + $annualInternetForWork + $annualEquipmentDep + $annualHomeOffice + $annualAdditional;

            $round = fn($v) => round((float)$v, 2);

            $data = [
                'electricity_monthly' => $round($monthlyElectricityCost),
                'electricity_annual' => $round($annualElectricityCost),

                'internet_monthly' => $round($monthlyInternetForWork),
                'internet_annual' => $round($annualInternetForWork),

                'equipment_depreciation_monthly' => $round($monthlyEquipmentDep),
                'equipment_depreciation_annual' => $round($annualEquipmentDep),

                'home_office_space_monthly' => $round($monthlyHomeOffice),
                'home_office_space_annual' => $round($annualHomeOffice),

                'additional_expenses_monthly' => $round($monthlyAdditional),
                'additional_expenses_annual' => $round($annualAdditional),

                'total_monthly' => $round($totalMonthly),
                'total_annual' => $round($totalAnnual),
            ];

            return $this->success('WFH cost calculation completed.', $data, 200);
        } catch (\Throwable $ex) {
            Log::error('Exception in Work_from_home_calculator::calculate', [
                'message' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error while computing WFH costs. See logs.',
                'error' => ['exception' => $ex->getMessage()],
                'data' => null
            ], 500);
        }
    }
}
