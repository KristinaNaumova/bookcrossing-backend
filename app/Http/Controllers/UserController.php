<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    function getMyInfo(Request $request) {
        try {
            $userId = $request['userInfo']['id'];
            $user = User::findOrFail($userId);

            echo $user->with('locations')->with('contacts')->first();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined user with id: ' . $userId);
        }
    }

    function getConcreteUserInfo($userId) {
        try {
            $user = User::findOrFail($userId);

            echo $user->with('locations')->first();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined user with id: ' . $userId);
        }
    }

    function updateUserLocations(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $validatedData = $request->validate([
            'locations' => 'nullable|array',
            'locations.*' => Rule::exists('locations', 'id'),
        ]);

        $user  = User::find($userId);

        foreach ($validatedData['locations'] as $location) {
            $user->locations()->attach($location);
        }
    }
}
