<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpParser\Node\Expr\AssignOp\Concat;

class UserController extends Controller
{
    function getMyInfo(Request $request) {
        try {
            $userId = $request['userInfo']['id'];
            $user = User::findOrFail($userId);

            echo $user->with('locations')->with('contacts')->with('genres')->where('id', $userId)->first();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined user with id: ' . $userId);
        }
    }

    function getConcreteUserInfo($userId) {
        try {
            $user = User::findOrFail($userId);

            echo $user->with('locations')->where('id', $userId)->first();
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

        DB::transaction(function () use ($user, $validatedData) {
            $user->locations()->detach();

            foreach ($validatedData['locations'] as $location) {
                $user->locations()->attach($location);
            }
        });
    }

    function updateUserContacts(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $validatedData = $request->validate([
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|string',
            'contacts.*.contact' => 'required|string',
        ]);

        DB::transaction(function () use ($userId, $validatedData) {
            Contact::where('user_id', $userId)->delete();

            foreach ($validatedData['contacts'] as $contact) {
                Contact::insert([
                    'user_id' => $userId,
                    'contact_type' => $contact['contact_type'],
                    'contact' => $contact['contact'],
                ]);
            }
        });
    }

    function getUserFavouriteGenres(Request $request)
    {
        $userId = $request['userInfo']['id'];
        $user = User::find($userId);

        return $user->genres()->get();
    }

    function updateUserFavouriteGenres(Request $request)
    {
        $userId = $request['userInfo']['id'];
        $user = User::find($userId);

        $validatedData = $request->validate([
            'genres' => 'nullable|array',
            'genres.*' => Rule::exists('genres', 'id'),
        ]);

        DB::transaction(function () use ($validatedData, $user) {
            $user->genres()->detach();

            foreach ($validatedData['genres'] as $genre) {
                $user->genres()->attach($genre);
            }
        });
    }
}
