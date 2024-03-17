<?php

namespace App\Modules\Ad\Models;

use App\Models\Genre;
use App\Models\Response;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'published_at', 'book_name', 'book_author', 'description', 'comment', 'deadline', 'status', 'type'];

    protected $hidden = ['pivot', 'created_at', 'updated_at'];

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }
}
