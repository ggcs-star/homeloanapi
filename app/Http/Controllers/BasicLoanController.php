<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BasicLoan;
use Illuminate\Support\Facades\DB;

class BasicLoanController extends Controller
{
    // List all loans
    public function index()
    {
        $loans = BasicLoan::all();
        return response()->json($loans);
    }

    // Create new loan
    public function store(Request $request)
    {
        $request->validate([
            'borrower_name' => 'required|string|max:255',
            'principal_amount' => 'required|numeric|min:1',
            'term_years' => 'required|integer|min:1',
            'interest_rate' => 'sometimes|numeric|min:0', // optional
        ]);

        // Agar interest_rate nahi bheja to database default use karega
        $loanData = $request->all();

        // Agar request me interest_rate nahi hai to default 12 set karo
        if (!isset($loanData['interest_rate']) || $loanData['interest_rate'] == null) {
            $loanData['interest_rate'] = 12.00;
        }

        $loan = BasicLoan::create($loanData);
        return response()->json($loan, 201);
    }

    // Show specific loan
    public function show($id)
    {
        $loan = BasicLoan::findOrFail($id);
        return response()->json($loan);
    }

    // Update loan
    public function update(Request $request, $id)
    {
        $loan = BasicLoan::findOrFail($id);

        $request->validate([
            'borrower_name' => 'sometimes|string|max:255',
            'principal_amount' => 'sometimes|numeric|min:1',
            'term_years' => 'sometimes|integer|min:1',
            'interest_rate' => 'sometimes|numeric|min:0',
        ]);

        $updateData = $request->all();

        // Agar interest_rate nahi bheja to default 12 set karo (optional)
        if (!isset($updateData['interest_rate']) || $updateData['interest_rate'] == null) {
            $updateData['interest_rate'] = 12.00;
        }

        $loan->update($updateData);
        return response()->json($loan);
    }

    // Delete loan
    public function destroy($id)
    {
        BasicLoan::destroy($id);
        return response()->json(null, 204);
    }

    // EMI / Loan Calculation
    public function calculate(Request $request)
    {
        $request->validate([
            'principal_amount' => 'required|numeric|min:1',
            'term_years' => 'required|integer|min:1',
            'interest_rate' => 'sometimes|numeric|min:0',
        ]);

        $principal = $request->input('principal_amount');
        $term = $request->input('term_years');
        $rate = $request->input('interest_rate');

        // Agar interest_rate nahi diya to default 12
        if (!$rate) {
            $rate = 15.00;
        }

        $monthlyRate = $rate / 12 / 100;
        $months = $term * 12;

        // Safe EMI calculation
        if ($monthlyRate == 0) {
            $emi = $principal / $months;
        } else {
            $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        }

        return response()->json([
            'principal_amount' => $principal,
            'interest_rate' => $rate,
            'term_years' => $term,
            'monthly_emi' => round($emi, 2)
        ]);
    }
}
