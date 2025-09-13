<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterestRate extends Model
{
    use HasFactory;

    protected $table = 'interest_rates';

    // ✅ Allow mass assignment
    protected $fillable = [
        'type',
        'rate',
    ];
}
