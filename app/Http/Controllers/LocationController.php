<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    function getAllLocations()
    {
        return Location::all();
    }
}
