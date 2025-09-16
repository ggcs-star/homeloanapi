<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        // Insert Example
        User::create([
            'name' => 'Piyush',
            'email' => 'test@example.com'
        ]);

        // Fetch Example
        $users = User::all();

        return response()->json($users);
    }
}
