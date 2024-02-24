<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    function login(Request $request) {
        try {
            $validatedData = $request->validate([
                'id' => 'required',
                'faculty_id' => 'required',
                'name' => 'required'
            ]);

            $user = User::where('id', $validatedData['id']);

            $tokensPair = (new TokenService)->getTokensPair($validatedData['id'], $validatedData['name']);

            if (!$user->exists()) {
                User::insert([
                    'id' => $validatedData['id'],
                    'faculty_id' => $validatedData['faculty_id'],
                    'name' => $validatedData['name'],
                    'access_token' => $tokensPair['access_token'],
                    'refresh_token' => $tokensPair['refresh_token'],
                ]);
            } else {
                $user->update([
                    'access_token' => $tokensPair['access_token'],
                    'refresh_token' => $tokensPair['refresh_token'],
                ]);
            }

            return $tokensPair;
        } catch (ValidationException $e) {
            abort(400, 'Validation exception');
        }
    }

    function refresh(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'refresh_token' => 'required',
            ]);

            $user = User::where('refresh_token', $validatedData['refresh_token']);

            if (!$user->exists()) {
                abort(403, 'Undefined user');
            }

            $tokensPair = (new TokenService)->getTokensPair($user->first()->id, $user->first()->name);

            $user->update([
                'access_token' => $tokensPair['access_token'],
                'refresh_token' => $tokensPair['refresh_token'],
            ]);

            return $tokensPair;
        } catch (ValidationException $e) {
            abort(400, 'Incorrect token');
        }
    }
}
