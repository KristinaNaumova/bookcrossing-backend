<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'is_new', 'message', 'user_id'];

    protected $hidden = ['updated_at', 'created_at', 'pivot'];
}
