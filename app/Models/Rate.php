<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Rate extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rates';
    protected $fillable = ['calculator', 'settings'];
}
