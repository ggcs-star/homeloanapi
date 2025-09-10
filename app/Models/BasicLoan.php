<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BasicLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrower_name',
        'principal_amount',
        'interest_rate',
        'term_years',
    ];
}
