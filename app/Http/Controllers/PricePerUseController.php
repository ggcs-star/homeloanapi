<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PricePerUseController extends Controller
{
    public function calculate(Request $request)
    {
        $items = $request->input('items', []);
        $results = [];

        foreach ($items as $item) {
            $name = $item['name'] ?? 'Unknown';
            $purchase_price = (float) ($item['purchase_price'] ?? 0);
            $additional_costs = (float) ($item['additional_costs'] ?? 0);
            $uses = (int) ($item['total_uses'] ?? 1);

            $total_cost = $purchase_price + $additional_costs;
            $price_per_use = ($uses > 0) ? $total_cost / $uses : 0;

            $results[] = [
                'item_name' => $name,
                'purchase_price' => $purchase_price,
                'additional_costs' => $additional_costs,
                'total_uses' => $uses,
                'total_cost' => $total_cost,
                'price_per_use' => round($price_per_use, 2)
            ];
        }

        return response()->json([
            'results' => $results
        ]);
    }
}
