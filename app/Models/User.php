<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class User extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'id', 'faculty', 'refresh_token', 'access_token', 'rating', 'is_banned'];

    protected $hidden = ['updated_at', 'created_at', 'refresh_token', 'access_token', 'pivot'];

    public $incrementing = false;

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function getFacultyAttribute($value): string
    {
        $facultyName = DB::table('faculties')->find($value);

        return $facultyName->name;
    }
}
