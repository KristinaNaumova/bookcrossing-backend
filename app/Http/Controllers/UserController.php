<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class UserController extends Controller
{
    function getUserInfo(Request $request) {
        try {
            $userId = $request['userInfo']['id'];
            $user = User::findOrFail($userId);

            echo $user->with('locations')->with('contacts')->first();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined user with id: ' . $userId);
        }
    }
}
