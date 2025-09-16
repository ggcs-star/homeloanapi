<?php

namespace App\Traits;

trait HasInterestRate
{
    public function getInterestRate($request, $default = 10)
    {
        if ($request->has('interest_rate')) {
            return [
                'value' => (float) $request->input('interest_rate'),
                'source' => 'user'
            ];
        } elseif ($request->has('annual_interest_rate')) {
            return [
                'value' => (float) $request->input('annual_interest_rate'),
                'source' => 'user'
            ];
        } else {
            return [
                'value' => $default,
                'source' => 'admin'
            ];
        }
    }
}
