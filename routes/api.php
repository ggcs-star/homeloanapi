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
// Route::post('/basic-loan', [BasicLoanController::class, 'calculate']);
Route::post('/advance-loan-calculator', [LoanAdvanceController::class, 'calculate']);
Route::post('/emi-vs-rent', [EmiVsRentController::class, 'emiVsRentProjection']);
Route::post('/debt/calc', [DebtCalculatorController::class, 'calculate'])
Route::post('/loans', [BasicLoanController::class, 'store']);
Route::post('/sip-calculate', [SipCalculatorController::class, 'calculate']);
