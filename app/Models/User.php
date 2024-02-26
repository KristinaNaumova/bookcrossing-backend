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

    protected $fillable = ['name', 'id', 'faculty', 'refresh_token', 'access_token', 'rating', 'is_banned', 'appraisers_number'];

    protected $hidden = ['updated_at', 'created_at', 'refresh_token', 'access_token', 'pivot', 'appraisers_number'];

    public $incrementing = false;

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function getFacultyAttribute($value): string
    {
        $facultyName = DB::table('faculties')->find($value);

        return $facultyName->name;
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }
}
