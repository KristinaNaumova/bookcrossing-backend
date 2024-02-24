<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'book_name', 'book_author', 'description', 'comment', 'deadline', 'status', 'type'];

    protected $hidden = ['pivot', 'created_at', 'updated_at'];

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }
}
