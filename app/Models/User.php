<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'faculty_id', 'id', 'refresh_token', 'access_token', 'rating'];

    protected $hidden = ['updated_at', 'created_at', 'refresh_token', 'access_token'];
}
