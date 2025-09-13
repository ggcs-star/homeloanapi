<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoanVsFdController;
use App\Http\Controllers\EquivalentRateController;
use App\Http\Controllers\LoanVsSwpController;
use App\Http\Controllers\EmiPrepayController;
use App\Http\Controllers\TimeValueController;
use App\Http\Controllers\BasicLoanController;
use App\Http\Controllers\LoanAdvanceController;
use App\Http\Controllers\EmiVsRentController;
use App\Http\Controllers\DebtCalculatorController;
use App\Http\Controllers\SipCalculatorController;
use App\Http\Controllers\TimeValueHourController;
use App\Http\Controllers\FutureValueController;
use App\Http\Controllers\CostOfDelayController;
use App\Http\Controllers\TaxSavingsCalculatorController;
use App\Http\Controllers\CompoundInterestController;
use App\Http\Controllers\MisCalculatorController;
use App\Http\Controllers\GratuityCalculatorController;
use App\Http\Controllers\ChildEducationController;
use App\Http\Controllers\CareerBreakCalculatorController;
use App\Http\Controllers\DualIncomeCalculatorController;
use App\Http\Controllers\DiyVsOutsourceController;
use App\Http\Controllers\FireCalcController;
use App\Http\Controllers\MarriageCalculatorController;
use App\Http\Controllers\RetirementCalculatorController;
use App\Http\Controllers\BudgetPlannerController;
use App\Http\Controllers\PricePerUseController;

use App\Http\Controllers\Any_insurance_policy_evaluation;

use App\Http\Controllers\Chit_fund_vs_Mutual_fund;
use App\Http\Controllers\Compound_interst;
use App\Http\Controllers\Currency_Depreciation_invesment;
use App\Http\Controllers\Dividend_vs_growth_invesment;
use App\Http\Controllers\Future_value_of_aniteam_inflation;
use App\Http\Controllers\Higher_education_cost_calculator;
use App\Http\Controllers\Job_switch_impact_calculator;
use App\Http\Controllers\Lic_policy_net_interstrate;
use App\Http\Controllers\Lifestyle_helth_roi;
use App\Http\Controllers\Real_return_after_tax;
use App\Http\Controllers\Relocation_opportunity_calculator;
use App\Http\Controllers\Senior_citizen_saving;
use App\Http\Controllers\Simple_interstrate_Calculator;
use App\Http\Controllers\Social_media_Timewaste;
use App\Http\Controllers\Swp_systematic_withdrawal_plan;
use App\Http\Controllers\Work_from_home_calculator;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/loan-vs-fd/calculate', [LoanVsFdController::class, 'calculate']);
Route::post('/equivalent-rate', [EquivalentRateController::class, 'calculate']);
Route::post('/loan-vs-swp', [LoanVsSwpController::class, 'calculate']);
Route::post('/emi-prepay-vs-invest', [EmiPrepayController::class, 'calculate']);
Route::post('/emi/inflation', [TimeValueController::class, 'calculateWithInflation']);
Route::post('/basic-loan', [BasicLoanController::class, 'calculate']);
Route::post('/advance-loan-calculator', [LoanAdvanceController::class, 'calculate']);
Route::post('/emi-vs-rent', [EmiVsRentController::class, 'emiVsRentProjection']);
Route::post('/debt/calc', [DebtCalculatorController::class, 'calculate']);
Route::get('loans', [BasicLoanController::class, 'index']);
Route::post('/loans', [BasicLoanController::class, 'store']);
Route::post('/sip-calculate', [SipCalculatorController::class, 'calculate']);
Route::post('/time-value-hour', [TimeValueHourController::class, 'calculate']);
Route::post('/future-value', [FutureValueController::class, 'calculate']);
Route::post('/sip/cost-of-delay', [CostOfDelayController::class, 'calculate']);
Route::post('/tax-savings-vs-investment', [TaxSavingsCalculatorController::class, 'calculate']);
Route::post('/compound-interest', [CompoundInterestController::class, 'calculate']);
Route::post('/mis-calculate', [MisCalculatorController::class, 'calculate']);
Route::post('/gratuity-calculate', [GratuityCalculatorController::class, 'calculate']);
Route::post('car/compare', [\App\Http\Controllers\CarLeaseVsBuyController::class, 'compare']);
Route::post('/tco/compare', [\App\Http\Controllers\TwoCarTcoController::class, 'compare']);
Route::post('/transportation/compare', [\App\Http\Controllers\TransportationCostController::class, 'compare']);
Route::post('/fuel/estimate', [\App\Http\Controllers\FuelCostController::class, 'estimate']);
Route::post('/child-education-calc', [ChildEducationController::class, 'calculate']);
Route::post('/career-break-calc', [CareerBreakCalculatorController::class, 'calculate']);
Route::post('/dual-income-calc', [DualIncomeCalculatorController::class, 'calculate']);
Route::post('/diy-vs-outsource', [DiyVsOutsourceController::class, 'calculate']);
Route::post('/fire-calc', [FireCalculatorController::class, 'calculate']);
Route::post('/fire-calc', [FireCalcController::class, 'calculate']);
Route::post('/marriage-calc', [MarriageCalculatorController::class, 'calculate']);
Route::post('/retirement-calc', [RetirementCalculatorController::class, 'calculate']);
Route::post('/budget-calc', [BudgetPlannerController::class, 'calculate']);
Route::post('/price-per-use', [PricePerUseController::class, 'calculate']);

Route::post('/Lifestyle_helth_roi', [Lifestyle_helth_roi::class, 'calculate']);
Route::post('/Higher_education_cost_calculator', [Higher_education_cost_calculator::class, 'calculate']);
Route::post('/Job_switch_impact_calculator', [Job_switch_impact_calculator::class, 'calculate']);
Route::post('/Work_from_home_calculator', [Work_from_home_calculator::class, 'calculate']);
Route::post('/Social_media_Timewaste', [Social_media_Timewaste::class, 'calculate']);
Route::post('/Any_insurance_policy_evaluation', [Any_insurance_policy_evaluation::class, 'calculate']);
Route::post('/Lic_policy_net_interstrate', [Lic_policy_net_interstrate::class, 'calculate']);
Route::post('/Relocation_opportunity_calculator', [Relocation_opportunity_calculator::class, 'calculate']);
Route::post('/Senior_citizen_saving', [Senior_citizen_saving::class, 'calculate']);
Route::post('/Currency_Depreciation_invesment', [Currency_Depreciation_invesment::class, 'calculate']);
Route::post('/Simple_interstrate_Calculator', [Simple_interstrate_Calculator::class, 'calculate']);
Route::post('/dividend-vs-growth', [Dividend_vs_growth_invesment::class, 'compare']);
Route::post('/Chit_fund_vs_Mutual_fund', [Chit_fund_vs_Mutual_fund::class, 'calculate']);
Route::post('/real-return-after-tax', [Real_return_after_tax::class, 'calculate']);
Route::post('/Swp_syatematic_withdrawal_plan', [Swp_systematic_withdrawal_plan::class, 'calculate']);
Route::post('/Future_value_of_aniteam_inflation', [Future_value_of_aniteam_inflation::class, 'calculate']);
